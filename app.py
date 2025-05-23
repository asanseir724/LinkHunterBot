import os
import logging
from flask import Flask, render_template, request, redirect, url_for, flash, jsonify
from link_manager import LinkManager
from scheduler import setup_scheduler
from bot import setup_bot
import json

# Configure logging
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

# Create the Flask app
app = Flask(__name__)
app.secret_key = os.environ.get("SESSION_SECRET", "default-secret-key-for-development")

# Initialize the link manager
link_manager = LinkManager()

# Initialize the bot
try:
    bot = setup_bot(link_manager)
    logger.info("Telegram bot initialized successfully")
except Exception as e:
    logger.error(f"Failed to initialize Telegram bot: {e}")
    bot = None

# Setup the scheduler
scheduler = setup_scheduler(bot, link_manager)
scheduler.start()
logger.info("Scheduler started")

@app.route('/')
def index():
    """Render the home page with stats"""
    stats = {
        'total_links': len(link_manager.get_all_links()),
        'total_channels': len(link_manager.get_channels()),
        'last_check': link_manager.get_last_check_time(),
        'next_check': scheduler.get_next_run_time()
    }
    return render_template('index.html', stats=stats)

@app.route('/channels', methods=['GET', 'POST'])
def channels():
    """View and manage channel sources"""
    if request.method == 'POST':
        channel = request.form.get('channel')
        if channel:
            if link_manager.add_channel(channel):
                flash(f"Channel {channel} added successfully", "success")
            else:
                flash(f"Channel {channel} already exists", "warning")
        return redirect(url_for('channels'))
    
    return render_template('channels.html', channels=link_manager.get_channels())

@app.route('/remove_channel/<channel>', methods=['POST'])
def remove_channel(channel):
    """Remove a channel from sources"""
    if link_manager.remove_channel(channel):
        flash(f"Channel {channel} removed successfully", "success")
    else:
        flash(f"Failed to remove channel {channel}", "danger")
    return redirect(url_for('channels'))

@app.route('/links')
def links():
    """View stored links"""
    all_links = link_manager.get_all_links()
    return render_template('links.html', links=all_links)

@app.route('/settings', methods=['GET', 'POST'])
def settings():
    """Configure bot settings"""
    if request.method == 'POST':
        interval = request.form.get('interval')
        try:
            interval = int(interval)
            if interval < 1:
                flash("Interval must be at least 1 minute", "danger")
            else:
                scheduler.update_interval(interval)
                link_manager.set_check_interval(interval)
                flash(f"Check interval updated to {interval} minutes", "success")
        except ValueError:
            flash("Invalid interval value", "danger")
        
        return redirect(url_for('settings'))
    
    return render_template('settings.html', 
                          interval=link_manager.get_check_interval(),
                          bot_status="Running" if bot else "Not Running")

@app.route('/check_now', methods=['POST'])
def check_now():
    """Trigger an immediate link check"""
    if bot:
        try:
            scheduler.run_job_now()
            flash("Link check initiated", "success")
        except Exception as e:
            logger.error(f"Failed to run immediate check: {e}")
            flash(f"Failed to run check: {str(e)}", "danger")
    else:
        flash("Bot is not running, cannot check links", "danger")
    
    return redirect(url_for('index'))

@app.route('/clear_links', methods=['POST'])
def clear_links():
    """Clear all stored links"""
    link_manager.clear_links()
    flash("All links cleared", "success")
    return redirect(url_for('links'))

@app.route('/api/links', methods=['GET'])
def api_links():
    """API endpoint to get all links"""
    return jsonify(link_manager.get_all_links())

@app.errorhandler(404)
def page_not_found(e):
    return render_template('404.html'), 404

@app.errorhandler(500)
def server_error(e):
    return render_template('500.html'), 500

# Cleanup
@app.teardown_appcontext
def shutdown_scheduler(exception=None):
    if scheduler.running:
        scheduler.shutdown()
        logger.info("Scheduler shut down")
