import os
import sys
from flask import Flask, render_template, request, redirect, url_for, flash, jsonify
from link_manager import LinkManager
from datetime import datetime
from logger import get_logger, get_all_logs, clear_logs

# Get application logger
logger = get_logger(__name__)

# Create the Flask app
app = Flask(__name__)
app.secret_key = os.environ.get("SESSION_SECRET", "default-secret-key-for-development")

# Initialize the link manager
link_manager = LinkManager()

# Mock some functionality for initial testing
bot_status = "Not Running"

@app.route('/')
def index():
    """Render the home page with stats"""
    stats = {
        'total_links': len(link_manager.get_all_links()),
        'total_channels': len(link_manager.get_channels()),
        'last_check': link_manager.get_last_check_time(),
        'next_check': "Not scheduled" 
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
    new_links = link_manager.get_new_links()
    return render_template('links.html', links=all_links, new_links=new_links)

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
                link_manager.set_check_interval(interval)
                flash(f"Check interval updated to {interval} minutes", "success")
        except ValueError:
            flash("Invalid interval value", "danger")
        
        return redirect(url_for('settings'))
    
    return render_template('settings.html', 
                          interval=link_manager.get_check_interval(),
                          bot_status=bot_status)

@app.route('/set_token', methods=['POST'])
def set_token():
    """Set the Telegram Bot Token"""
    token = request.form.get('token')
    if not token:
        flash("Please provide a valid token", "danger")
        return redirect(url_for('settings'))
    
    # Save the token as an environment variable
    os.environ["TELEGRAM_BOT_TOKEN"] = token
    logger.info("Telegram Bot Token set, attempting to initialize bot")
    
    # Try to initialize the bot
    try:
        from bot import setup_bot
        # Initialize the bot with our link manager
        bot_instance = setup_bot(link_manager)
        
        # If we get here, the bot was initialized successfully
        global bot_status
        bot_status = "Running"
        logger.info("Bot initialized successfully")
        
        flash("Telegram Bot Token set successfully. Bot is now running.", "success")
    except Exception as e:
        logger.error(f"Failed to initialize bot: {str(e)}")
        logger.error(f"Exception type: {type(e)}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")
        
        flash(f"Failed to initialize bot: {str(e)}", "danger")
    
    return redirect(url_for('settings'))

@app.route('/check_now', methods=['POST'])
def check_now():
    """Trigger an immediate link check"""
    try:
        logger.info("Manual check requested")
        
        if bot_status != "Running":
            logger.warning("Bot is not running, cannot perform check")
            flash("Bot is not running. Please set up the token first.", "warning")
            return redirect(url_for('settings'))
        
        # Try to perform a check using the scheduler
        try:
            from bot import check_channels_for_links, setup_bot
            
            # Initialize bot if needed
            bot_token = os.environ.get("TELEGRAM_BOT_TOKEN")
            if not bot_token:
                raise ValueError("Telegram bot token not set")
            
            # Create a bot instance
            bot = setup_bot(link_manager)
            if not bot:
                raise ValueError("Failed to initialize bot")
            
            # Run the check directly (now synchronized)
            logger.info("Starting manual link check")
            result = check_channels_for_links(bot, link_manager)
            
            logger.info(f"Manual check complete. Found {result} new links.")
            flash(f"Check complete! Found {result} new links.", "success")
                
        except Exception as e:
            logger.error(f"Failed to run check: {str(e)}")
            logger.error(f"Exception type: {type(e)}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
            link_manager.update_last_check_time()  # At least update timestamp
            flash(f"Error during check: {str(e)}", "danger")
    
    except Exception as e:
        logger.error(f"Failed to run immediate check: {str(e)}")
        logger.error(f"Exception type: {type(e)}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")
        flash(f"Failed to run check: {str(e)}", "danger")
    
    return redirect(url_for('index'))

@app.route('/clear_links', methods=['POST'])
def clear_links():
    """Clear all stored links"""
    link_manager.clear_links()
    flash("All links cleared", "success")
    return redirect(url_for('links'))

@app.route('/clear_new_links', methods=['POST'])
def clear_new_links():
    """Clear only the new links list"""
    link_manager.clear_new_links()
    flash("New links cleared", "success")
    return redirect(url_for('links'))

@app.route('/export_all_links', methods=['GET'])
def export_all_links():
    """Export all links to Excel"""
    try:
        filename = link_manager.export_all_links_to_excel()
        if filename:
            flash(f"All links exported to Excel successfully", "success")
            return redirect(url_for('links'))
        else:
            flash("Failed to export links", "danger")
            return redirect(url_for('links'))
    except Exception as e:
        logger.error(f"Error exporting all links: {str(e)}")
        flash(f"Error exporting links: {str(e)}", "danger")
        return redirect(url_for('links'))

@app.route('/export_new_links', methods=['GET'])
def export_new_links():
    """Export new links to Excel"""
    try:
        filename = link_manager.export_new_links_to_excel()
        if filename:
            flash(f"New links exported to Excel successfully", "success")
            return redirect(url_for('links'))
        else:
            flash("Failed to export links", "danger")
            return redirect(url_for('links'))
    except Exception as e:
        logger.error(f"Error exporting new links: {str(e)}")
        flash(f"Error exporting links: {str(e)}", "danger")
        return redirect(url_for('links'))

@app.route('/logs')
def logs():
    """View system logs"""
    all_logs = get_all_logs()
    return render_template('logs.html', logs=all_logs)

@app.route('/clear_logs', methods=['POST'])
def clear_logs_route():
    """Clear all logs"""
    if clear_logs():
        flash("All logs cleared successfully", "success")
    else:
        flash("Failed to clear logs", "danger")
    return redirect(url_for('logs'))

@app.route('/refresh_logs', methods=['POST'])
def refresh_logs():
    """Refresh logs page"""
    return redirect(url_for('logs'))

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

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
