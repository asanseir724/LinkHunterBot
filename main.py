import os
import sys
import time
import threading
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

# Initialize bot status
bot_status = "Not Running"

# Scheduler background thread
scheduler_thread = None
scheduler_running = False

def periodic_check():
    """Run periodic link checking"""
    global scheduler_running, last_check_result
    
    logger.info(f"Starting automatic scheduler with {link_manager.get_check_interval()} minute intervals")
    
    while scheduler_running:
        # Only run if bot is initialized
        if bot_status == "Running" and "TELEGRAM_BOT_TOKEN" in os.environ:
            logger.info("Scheduler: Running automatic check")
            
            try:
                from bot import check_channels_for_links, setup_bot
                
                # Create a bot instance
                bot = setup_bot(link_manager)
                if not bot:
                    logger.error("Scheduler: Failed to initialize bot")
                    time.sleep(60)  # Wait a minute before retrying
                    continue
                
                # Get the total number of channels
                total_channels = len(link_manager.get_channels())
                
                # Use fixed number of channels per batch
                max_channels = 20
                
                # Run the check
                logger.info("Scheduler: Starting link check")
                result = check_channels_for_links(bot, link_manager, max_channels)
                
                logger.info(f"Scheduler: Check complete. Found {result} new links.")
                
                # Save the check result
                with lock:
                    last_check_result.update({
                        'timestamp': datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                        'new_links': result,
                        'status': 'completed',
                        'total_channels': total_channels,
                        'channels_checked': min(total_channels, max_channels)
                    })
                
            except Exception as e:
                logger.error(f"Scheduler: Error in automatic check: {str(e)}")
                import traceback
                logger.error(f"Traceback: {traceback.format_exc()}")
            
        # Sleep for the configured interval
        check_interval = link_manager.get_check_interval()
        logger.info(f"Scheduler: Sleeping for {check_interval} minutes until next check")
        
        # Sleep in smaller increments to allow faster shutdown
        for _ in range(check_interval * 60 // 10):  # 10-second chunks
            if not scheduler_running:
                break
            time.sleep(10)

def init_scheduler():
    """Initialize and start the background scheduler"""
    global scheduler_thread, scheduler_running
    
    # Don't start if already running
    if scheduler_running and scheduler_thread and scheduler_thread.is_alive():
        logger.info("Scheduler already running")
        return
    
    # Set the flag and start the thread
    scheduler_running = True
    scheduler_thread = threading.Thread(target=periodic_check)
    scheduler_thread.daemon = True
    scheduler_thread.start()
    
    logger.info("Automatic scheduler started")

# Initialize the bot and scheduler if we have a token
if os.environ.get("TELEGRAM_BOT_TOKEN"):
    try:
        from bot import setup_bot
        # Initialize the bot with our link manager
        bot_instance = setup_bot(link_manager)
        if bot_instance:
            bot_status = "Running"
            logger.info("Bot initialized automatically with saved token")
            # Start the scheduler for automatic checking
            init_scheduler()
    except Exception as e:
        logger.error(f"Failed to auto-initialize bot: {str(e)}")
        logger.error(f"Exception type: {type(e)}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")

@app.route('/')
def index():
    """Render the home page with stats"""
    from datetime import datetime, timedelta
    
    # Calculate next check time based on last check and interval
    next_check = "زمان‌بندی نشده"
    if bot_status == "Running" and link_manager.get_last_check_time():
        try:
            last_check_dt = datetime.fromisoformat(link_manager.last_check)
            interval_mins = link_manager.get_check_interval()
            next_check_dt = last_check_dt + timedelta(minutes=interval_mins)
            next_check = next_check_dt.strftime("%Y-%m-%d %H:%M:%S")
        except Exception as e:
            logger.error(f"Error calculating next check time: {str(e)}")
    
    stats = {
        'total_links': len(link_manager.get_all_links()),
        'total_channels': len(link_manager.get_channels()),
        'last_check': link_manager.get_last_check_time(),
        'next_check': next_check
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

@app.route('/add_bulk_channels', methods=['POST'])
def add_bulk_channels():
    """Add multiple channels at once"""
    bulk_channels = request.form.get('bulk_channels', '')
    if not bulk_channels:
        flash("No channels provided", "warning")
        return redirect(url_for('channels'))
    
    # Split by commas or newlines
    channels_to_add = []
    if ',' in bulk_channels:
        channels_to_add = [c.strip() for c in bulk_channels.split(',') if c.strip()]
    else:
        channels_to_add = [c.strip() for c in bulk_channels.splitlines() if c.strip()]
    
    # Process each channel
    added_count = 0
    already_exists_count = 0
    invalid_count = 0
    
    for channel in channels_to_add:
        # Note: No need to pre-process channel links here
        # The add_channel method in LinkManager will handle normalization of URLs and @ symbols
        result = link_manager.add_channel(channel)
        if result is True:
            added_count += 1
        elif result is False:
            already_exists_count += 1
        else:
            # This happens when add_channel returns None or False due to invalid format
            invalid_count += 1
    
    # Display results
    if added_count > 0:
        flash(f"Added {added_count} new channels successfully", "success")
    if already_exists_count > 0:
        flash(f"{already_exists_count} channels already existed", "info")
    if invalid_count > 0:
        flash(f"{invalid_count} invalid channel format(s) detected", "warning")
    if added_count == 0 and already_exists_count == 0 and invalid_count == 0:
        flash("No valid channels found", "warning")
    
    return redirect(url_for('channels'))

@app.route('/remove_all_channels', methods=['POST'])
def remove_all_channels():
    """Remove all channels from monitoring"""
    count = len(link_manager.get_channels())
    link_manager.remove_all_channels()
    flash(f"Removed all {count} channels", "success")
    return redirect(url_for('channels'))

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
    # Check if we have a category filter
    category = request.args.get('category')
    
    if category:
        # Get links for this category only
        category_links = link_manager.get_links_by_category(category)
        all_links = category_links
        category_display = category
    else:
        # Get all links
        all_links = link_manager.get_all_links()
        category_display = None
        
    new_links = link_manager.get_new_links()
    categories = link_manager.get_categories()
    
    # Create a mapping of links to their categories for display
    link_categories = {}
    for cat, cat_links in link_manager.get_links_by_category().items():
        for link in cat_links:
            link_categories[link] = cat
    
    return render_template('links.html', 
                          links=all_links, 
                          new_links=new_links,
                          categories=categories,
                          current_category=category_display,
                          link_categories=link_categories)

@app.route('/settings', methods=['GET', 'POST'])
def settings():
    """Configure bot settings"""
    if request.method == 'POST':
        # Handle interval update
        if 'interval' in request.form:
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
        
        # Handle message count update
        if 'message_count' in request.form:
            message_count = request.form.get('message_count')
            try:
                message_count = int(message_count)
                if message_count < 1:
                    flash("Message count must be at least 1", "danger")
                elif message_count > 50:
                    flash("Message count cannot exceed 50 to avoid rate limits", "danger")
                else:
                    link_manager.check_message_count = message_count
                    link_manager.save_data()
                    flash(f"Message count updated to {message_count}", "success")
            except ValueError:
                flash("Invalid message count value", "danger")
                
        # Handle auto-discover toggle
        if 'auto_discover' in request.form:
            auto_discover = request.form.get('auto_discover') == 'on'
            link_manager.auto_discover = auto_discover
            link_manager.save_data()
            flash(f"Auto-discover new channels: {'enabled' if auto_discover else 'disabled'}", "success")
        
        return redirect(url_for('settings'))
    
    # Get available categories
    categories = link_manager.get_categories()
    
    return render_template('settings.html', 
                          interval=link_manager.get_check_interval(),
                          bot_status=bot_status,
                          categories=categories,
                          message_count=link_manager.check_message_count,
                          auto_discover=link_manager.auto_discover)

@app.route('/set_token', methods=['POST'])
def set_token():
    """Set the Telegram Bot Token"""
    token = request.form.get('token')
    if not token:
        flash("لطفا توکن معتبر وارد کنید", "danger")
        return redirect(url_for('settings'))
    
    # Save the token permanently in link_manager
    link_manager.set_telegram_token(token)
    logger.info("Telegram Bot Token set and saved, attempting to initialize bot")
    
    # Try to initialize the bot
    try:
        from bot import setup_bot
        # Initialize the bot with our link manager
        bot_instance = setup_bot(link_manager)
        
        # If we get here, the bot was initialized successfully
        global bot_status
        bot_status = "Running"
        logger.info("Bot initialized successfully")
        
        flash("توکن تلگرام با موفقیت ذخیره شد. ربات اکنون در حال اجراست.", "success")
        
        # Start the scheduler to periodically check for new links
        init_scheduler()
    except Exception as e:
        logger.error(f"Failed to initialize bot: {str(e)}")
        logger.error(f"Exception type: {type(e)}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")
        
        flash(f"خطا در راه‌اندازی ربات: {str(e)}", "danger")
    
    return redirect(url_for('settings'))

# Initialize global variables for thread coordination and status tracking
import threading
lock = threading.Lock()

# Store the last check result for API access
last_check_result = {
    'timestamp': None,
    'status': 'not_run',
    'new_links': 0,
    'total_channels': 0,
    'channels_checked': 0
}

@app.route('/check_now', methods=['POST'])
def check_now():
    """Trigger an immediate link check"""
    try:
        logger.info("Manual check requested")
        
        if bot_status != "Running":
            logger.warning("Bot is not running, cannot perform check")
            flash("Bot is not running. Please set up the token first.", "warning")
            return redirect(url_for('settings'))
        
        # Create a background task by running the check operation immediately
        # but return a response to the user right away
        try:
            # Start a background thread to run the check
            import threading
            
            def run_background_check():
                """Run the link check in a background thread"""
                global last_check_result
                
                try:
                    from bot import check_channels_for_links, setup_bot
                    
                    # Initialize bot
                    bot_token = os.environ.get("TELEGRAM_BOT_TOKEN")
                    if not bot_token:
                        logger.error("Telegram bot token not set")
                        return
                    
                    # Create a bot instance
                    bot = setup_bot(link_manager)
                    if not bot:
                        logger.error("Failed to initialize bot")
                        return
                    
                    # Get the total number of channels
                    total_channels = len(link_manager.get_channels())
                    
                    # Adjust max_channels based on total channels
                    max_channels = 20
                    if total_channels > 50:
                        max_channels = 30  # Handle more channels in one batch for manual check
                        logger.info(f"Large number of channels ({total_channels}), using batch size of {max_channels}")
                    
                    # Run the check in the background thread
                    logger.info("Starting background link check")
                    result = check_channels_for_links(bot, link_manager, max_channels)
                    
                    logger.info(f"Background check complete. Found {result} new links.")
                    
                    # Save the check result for later retrieval
                    with lock:
                        last_check_result.update({
                            'timestamp': datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                            'new_links': result,
                            'status': 'completed',
                            'total_channels': total_channels,
                            'channels_checked': min(total_channels, max_channels)
                        })
                    
                except Exception as e:
                    logger.error(f"Error in background check: {str(e)}")
                    import traceback
                    logger.error(f"Traceback: {traceback.format_exc()}")
                    
                    # Update the check result with error
                    with lock:
                        last_check_result.update({
                            'timestamp': datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                            'status': 'error',
                            'error': str(e)
                        })
                    
                    # At least update timestamp
                    link_manager.update_last_check_time()
            
            # Start the background thread
            background_thread = threading.Thread(target=run_background_check)
            background_thread.daemon = True  # Make sure thread doesn't block app exit
            background_thread.start()
            
            # Let user know check has been started
            flash("درحال استخراج لینک‌ها در پس‌زمینه. نتایج به زودی قابل مشاهده خواهند بود.", "info")
            
        except Exception as e:
            logger.error(f"Failed to start background check: {str(e)}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
            flash(f"Error starting check: {str(e)}", "danger")
    
    except Exception as e:
        logger.error(f"Failed to process check request: {str(e)}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")
        flash(f"Failed to start check: {str(e)}", "danger")
    
    return redirect(url_for('index'))

@app.route('/api/check_status', methods=['GET'])
def api_check_status():
    """API endpoint to get the status of the latest check"""
    return jsonify(last_check_result)

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
        # Check if a category filter was provided
        category = request.args.get('category', None)
        
        if category:
            filename = link_manager.export_all_links_to_excel(category=category)
            category_display = f" - {category}"
        else:
            filename = link_manager.export_all_links_to_excel()
            category_display = ""
            
        if filename:
            # Return a direct download link
            full_path = os.path.join('static/exports', filename)
            download_url = url_for('static', filename=f'exports/{filename}')
            flash(f'تمام لینک ها{category_display} با موفقیت به اکسل صادر شدند. <a href="{download_url}" class="alert-link">برای دانلود کلیک کنید</a>', "success")
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
            # Return a direct download link
            full_path = os.path.join('static/exports', filename)
            download_url = url_for('static', filename=f'exports/{filename}')
            flash(f'New links exported to Excel successfully. <a href="{download_url}" class="alert-link">Click here to download</a>', "success")
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
    # Initialize the scheduler if we have a token in the environment
    if os.environ.get("TELEGRAM_BOT_TOKEN"):
        bot_status = "Running"
        init_scheduler()
        
    app.run(host="0.0.0.0", port=5000, debug=True)
