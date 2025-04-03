import os
import sys
import time
import re
import threading
from datetime import datetime
from flask import Flask, render_template, request, redirect, url_for, flash, jsonify
from link_manager import LinkManager
from datetime import datetime
from logger import get_logger, get_all_logs, clear_logs
from notification_utils import update_sms_settings, get_sms_settings, should_send_notification
from send_message import send_notification
from avalai_api import avalai_client
from web_crawler import extract_links_from_websites

# Get application logger
logger = get_logger(__name__)

# Create the Flask app
app = Flask(__name__)
app.secret_key = os.environ.get("SESSION_SECRET", "default-secret-key-for-development")

# Import the accounts blueprint
from account_routes import accounts_bp, setup_account_scheduler

# Register blueprints
app.register_blueprint(accounts_bp)

# Initialize the link manager
link_manager = LinkManager()

# Initialize bot status
bot_status = "Not Running"

# Scheduler background thread
scheduler_thread = None
scheduler_running = False

# Initialize bot instance
bot_instance = None

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
                
                # Get the total number of channels and websites
                total_channels = len(link_manager.get_channels())
                total_websites = len(link_manager.get_websites())
                
                # Process all channels without limits
                max_channels = total_channels  # No channel limit
                
                # Run the check for channels
                logger.info("Scheduler: Starting link check for channels")
                channel_result = check_channels_for_links(bot, link_manager, max_channels)
                
                # Run the check for websites
                logger.info("Scheduler: Starting link check for websites")
                website_result = check_websites_for_links(link_manager)
                
                # Total result from both checks
                result = channel_result + website_result
                
                logger.info(f"Scheduler: Check complete. Found {result} new links ({channel_result} from channels, {website_result} from websites).")
                
                # Save the check result
                with lock:
                    last_check_result.update({
                        'timestamp': datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                        'new_links': result,
                        'status': 'completed',
                        'total_channels': total_channels,
                        'channels_checked': min(total_channels, max_channels),
                        'total_websites': total_websites,
                        'websites_checked': total_websites,
                        'websites_links': website_result
                    })
                
                # Check if we should send an SMS notification about new links
                if result > 0 and should_send_notification(result):
                    sms_settings = get_sms_settings()
                    logger.info(f"Sending SMS notification about {result} new links to {sms_settings['phone_number']}")
                    
                    try:
                        notif_result = send_notification(
                            sms_settings['phone_number'], 
                            result
                        )
                        
                        logger.info(f"SMS notification sent: {notif_result}")
                    except Exception as e:
                        logger.error(f"Failed to send SMS notification: {str(e)}")
                else:
                    logger.debug(f"Not sending notification for {result} new links")
                
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
            
            # Start polling for direct messages
            logger.critical("[DIRECT_API_DEBUG] Starting direct API polling for private messages")
            bot_instance.start_polling()
            logger.critical("[DIRECT_API_DEBUG] Direct API polling started successfully")
            
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
    from user_accounts import AccountManager
    
    # Get account manager for user accounts stats
    account_manager = AccountManager()
    active_accounts = account_manager.get_active_accounts()
    
    # Calculate next check time based on last check and interval
    next_check = "زمان‌بندی نشده"
    if bot_status == "Running" and link_manager.get_last_check_time():
        try:
            # Make sure we have a valid string for datetime.fromisoformat
            last_check_time = link_manager.last_check
            if last_check_time and isinstance(last_check_time, str):
                last_check_dt = datetime.fromisoformat(last_check_time)
                interval_mins = link_manager.get_check_interval()
                next_check_dt = last_check_dt + timedelta(minutes=interval_mins)
                next_check = next_check_dt.strftime("%Y-%m-%d %H:%M:%S")
        except Exception as e:
            logger.error(f"Error calculating next check time: {str(e)}")
    
    # Get stats from latest check results
    groups_checked = 0
    websites_checked = 0
    websites_links = 0
    with lock:
        groups_checked = last_check_result.get('user_groups_checked', 0)
        websites_checked = last_check_result.get('websites_checked', 0)
        websites_links = last_check_result.get('websites_links', 0)
    
    stats = {
        'total_links': len(link_manager.get_all_links()),
        'total_channels': len(link_manager.get_channels()),
        'total_websites': len(link_manager.get_websites()),
        'last_check': link_manager.get_last_check_time(),
        'next_check': next_check,
        'user_accounts_count': len(active_accounts),
        'groups_checked': groups_checked,
        'websites_checked': websites_checked,
        'websites_links': websites_links
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

@app.route('/websites', methods=['GET', 'POST'])
def websites():
    """View and manage website sources for crawling"""
    if request.method == 'POST':
        website = request.form.get('website')
        category = request.form.get('category', 'عمومی')
        
        if website:
            if link_manager.add_website(website, category):
                flash(f"Website {website} added successfully", "success")
            else:
                flash(f"Website {website} already exists", "warning")
        return redirect(url_for('websites'))
    
    return render_template('websites.html', 
                          websites=link_manager.get_websites(),
                          website_categories=link_manager.get_website_categories(),
                          categories=link_manager.get_categories(),
                          scroll_count=link_manager.get_scroll_count())

@app.route('/add_bulk_websites', methods=['POST'])
def add_bulk_websites():
    """Add multiple websites at once"""
    bulk_websites = request.form.get('bulk_websites', '')
    category = request.form.get('category', 'عمومی')
    
    if not bulk_websites:
        flash("No websites provided", "warning")
        return redirect(url_for('websites'))
    
    # Split by commas or newlines
    websites_to_add = []
    if ',' in bulk_websites:
        websites_to_add = [w.strip() for w in bulk_websites.split(',') if w.strip()]
    else:
        websites_to_add = [w.strip() for w in bulk_websites.splitlines() if w.strip()]
    
    # Process each website
    added_count = 0
    already_exists_count = 0
    invalid_count = 0
    
    for website in websites_to_add:
        # Add the website
        result = link_manager.add_website(website, category)
        if result is True:
            added_count += 1
        elif result is False:
            already_exists_count += 1
        else:
            # This happens when add_website returns None or False due to invalid format
            invalid_count += 1
    
    # Display results
    if added_count > 0:
        flash(f"Added {added_count} new websites successfully", "success")
    if already_exists_count > 0:
        flash(f"{already_exists_count} websites already existed", "info")
    if invalid_count > 0:
        flash(f"{invalid_count} invalid website format(s) detected", "warning")
    if added_count == 0 and already_exists_count == 0 and invalid_count == 0:
        flash("No valid websites found", "warning")
    
    return redirect(url_for('websites'))

@app.route('/remove_all_websites', methods=['POST'])
def remove_all_websites():
    """Remove all websites from monitoring"""
    count = len(link_manager.get_websites())
    link_manager.remove_all_websites()
    flash(f"Removed all {count} websites", "success")
    return redirect(url_for('websites'))

@app.route('/remove_website/<path:website>', methods=['POST'])
def remove_website(website):
    """Remove a website from sources"""
    if link_manager.remove_website(website):
        flash(f"Website removed successfully", "success")
    else:
        flash(f"Failed to remove website", "danger")
    return redirect(url_for('websites'))

@app.route('/set_scroll_count', methods=['POST'])
def set_scroll_count():
    """Set scroll count for website crawling"""
    scroll_count = request.form.get('scroll_count')
    try:
        scroll_count = int(scroll_count)
        if scroll_count < 0:
            flash("Scroll count must be at least 0", "danger")
        else:
            link_manager.set_scroll_count(scroll_count)
            flash(f"Scroll count updated to {scroll_count}", "success")
    except ValueError:
        flash("Invalid scroll count value", "danger")
    
    return redirect(url_for('websites'))

@app.route('/set_website_category', methods=['POST'])
def set_website_category():
    """Set or update category for a website"""
    website = request.form.get('website')
    category = request.form.get('category')
    
    if not website or not category:
        flash("Both website and category are required", "danger")
    else:
        if link_manager.set_website_category(website, category):
            flash(f"Category for {website} updated to '{category}'", "success")
        else:
            flash(f"Failed to update category for {website}", "danger")
    
    return redirect(url_for('websites'))

@app.route('/avalai-settings', methods=['GET'])
def avalai_settings():
    """Configure AI settings for Avalai integration"""
    settings = avalai_client.get_settings()
    return render_template('avalai_settings.html', settings=settings)

@app.route('/update_avalai_settings', methods=['POST'])
def update_avalai_settings():
    """Update Avalai API settings"""
    # Get form data
    enabled = request.form.get('enabled') == 'on'
    api_key = request.form.get('api_key')
    default_prompt = request.form.get('default_prompt')
    respond_to_all_messages = request.form.get('respond_to_all_messages') == 'on'
    
    # Parse numeric values
    try:
        max_tokens = int(request.form.get('max_tokens', 500))
        temperature = float(request.form.get('temperature', 0.7))
    except ValueError:
        max_tokens = 500
        temperature = 0.7
    
    # Validate values
    if max_tokens < 50:
        max_tokens = 50
    elif max_tokens > 4000:
        max_tokens = 4000
        
    if temperature < 0:
        temperature = 0
    elif temperature > 1:
        temperature = 1
        
    # Update settings
    settings = {
        'enabled': enabled,
        'api_key': api_key,
        'default_prompt': default_prompt,
        'max_tokens': max_tokens,
        'temperature': temperature,
        'respond_to_all_messages': respond_to_all_messages
    }
    
    if avalai_client.update_settings(settings):
        flash("تنظیمات هوش مصنوعی با موفقیت به‌روزرسانی شد", "success")
    else:
        flash("خطا در به‌روزرسانی تنظیمات هوش مصنوعی", "danger")
    
    return redirect(url_for('avalai_settings'))

@app.route('/clear_avalai_history', methods=['GET'])
def clear_avalai_history():
    """Clear Avalai chat history"""
    if avalai_client.clear_chat_history():
        flash("تاریخچه گفتگوها با موفقیت پاک شد", "success")
    else:
        flash("خطا در پاک کردن تاریخچه گفتگوها", "danger")
    
    return redirect(url_for('avalai_settings'))

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
        
        # Handle SMS notification settings
        if 'sms_notifications' in request.form:
            # Get form data
            sms_enabled = request.form.get('sms_notifications') == 'on'
            phone_number = request.form.get('phone_number')
            min_links = request.form.get('min_links_for_notification', 5)
            
            try:
                min_links = int(min_links)
                if min_links < 1:
                    min_links = 1
            except ValueError:
                min_links = 5
                
            # Update settings
            if sms_enabled and not phone_number:
                flash("Phone number is required for SMS notifications", "danger")
            else:
                # Check if Twilio is configured
                if sms_enabled:
                    twilio_sid = os.environ.get("TWILIO_ACCOUNT_SID")
                    twilio_token = os.environ.get("TWILIO_AUTH_TOKEN")
                    twilio_phone = os.environ.get("TWILIO_PHONE_NUMBER")
                    
                    if not all([twilio_sid, twilio_token, twilio_phone]):
                        flash("Twilio is not fully configured. Please set up TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, and TWILIO_PHONE_NUMBER environment variables.", "warning")
                
                # Update settings
                update_sms_settings(sms_enabled, phone_number, min_links)
                link_manager.save_data()  # Save to persist in JSON file
                flash("SMS notification settings updated", "success")
        
        return redirect(url_for('settings'))
    
    # Get available categories
    categories = link_manager.get_categories()
    
    # Get all tokens for display
    all_tokens = link_manager.get_all_telegram_tokens()
    
    # Get SMS notification settings
    sms_settings = get_sms_settings()
    
    return render_template('settings.html', 
                          interval=link_manager.get_check_interval(),
                          bot_status=bot_status,
                          categories=categories,
                          message_count=link_manager.check_message_count,
                          auto_discover=link_manager.auto_discover,
                          all_tokens=all_tokens,
                          sms_notifications=sms_settings['enabled'],
                          phone_number=sms_settings['phone_number'],
                          min_links_for_notification=sms_settings['min_links'],
                          twilio_configured=sms_settings['twilio_configured'])

@app.route('/category_keywords', methods=['GET'])
def category_keywords():
    """View category keywords"""
    category = request.args.get('category')
    categories = link_manager.get_categories()
    
    if not category and categories:
        # Default to first category if none specified
        category = categories[0]
    
    keywords = []
    if category:
        keywords = link_manager.get_category_keywords(category)
    
    return render_template('category_keywords.html',
                          categories=categories,
                          current_category=category,
                          keywords=keywords)

@app.route('/update_category_keywords', methods=['POST'])
def update_category_keywords():
    """Update keywords for a category"""
    category = request.form.get('category')
    keywords_text = request.form.get('keywords', '')
    
    if not category:
        flash("No category specified", "danger")
        return redirect(url_for('category_keywords'))
    
    # Split keywords by comma or newline and clean them
    keywords = []
    for keyword in re.split(r',|\n', keywords_text):
        keyword = keyword.strip()
        if keyword:  # Only add non-empty keywords
            keywords.append(keyword)
            
    # Update keywords
    success = link_manager.update_category_keywords(category, keywords)
    
    if success:
        flash(f"Keywords for category '{category}' updated successfully", "success")
    else:
        flash(f"Failed to update keywords for category '{category}'", "danger")
        
    return redirect(url_for('category_keywords', category=category))

@app.route('/set_token', methods=['POST'])
def set_token():
    """Set the Telegram Bot Token"""
    token = request.form.get('token')
    if not token:
        flash("لطفا توکن معتبر وارد کنید", "danger")
        return redirect(url_for('settings'))
    
    # Add the token to rotation in link_manager
    if not link_manager.add_telegram_token(token):
        flash("این توکن قبلاً اضافه شده است", "warning")
        return redirect(url_for('settings'))
    
    logger.info("Telegram Bot Token added to rotation, attempting to initialize bot")
    
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

@app.route('/remove_token', methods=['POST'])
def remove_token():
    """Remove a Telegram Bot Token from rotation"""
    token = request.form.get('token')
    if not token:
        flash("توکن مشخص نشده است", "danger")
        return redirect(url_for('settings'))
    
    # Remove the token from rotation
    if link_manager.remove_telegram_token(token):
        flash("توکن با موفقیت حذف شد", "success")
    else:
        flash("این توکن در سیستم موجود نیست", "warning")
    
    # Get remaining tokens
    remaining_tokens = link_manager.get_all_telegram_tokens()
    
    # If all tokens removed, update bot status
    if not remaining_tokens:
        global bot_status
        bot_status = "Not Running"
        flash("همه توکن‌ها حذف شدند. ربات غیرفعال شد.", "warning")
    
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
    'channels_checked': 0,
    'user_groups_checked': 0,
    'user_accounts_checked': 0,
    'websites_checked': 0,
    'total_websites': 0,
    'websites_links': 0
}

def check_websites_for_links(link_manager):
    """
    Check monitored websites for new links
    
    Args:
        link_manager: The LinkManager instance
    
    Returns:
        int: Total number of new links found
    """
    # Get the websites to check
    websites = link_manager.get_websites()
    if not websites:
        logger.info("No websites configured for monitoring")
        return 0
        
    logger.info(f"Checking {len(websites)} websites for Telegram links")
    
    try:
        # Get the scroll count
        scroll_count = link_manager.get_scroll_count()
        
        # Extract links from all websites
        results = extract_links_from_websites(websites, scroll_count)
        
        # Track total new links found
        total_new_links = 0
        
        # Process each website's links
        for website_url, links in results.items():
            logger.info(f"Found {len(links)} links on {website_url}")
            
            # Get the website content for category detection
            website_content = None  # We don't have the content here, just the links
            
            # Add each link
            new_links_count = 0
            for link in links:
                if link_manager.add_website_link(link, website_url, website_content):
                    new_links_count += 1
                    
            logger.info(f"Added {new_links_count} new links from {website_url}")
            total_new_links += new_links_count
            
        # Update the last check time
        link_manager.update_last_check_time()
        logger.info(f"Found {total_new_links} new links from websites")
        return total_new_links
        
    except Exception as e:
        logger.error(f"Error checking websites for links: {str(e)}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")
        return 0

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
                    
                    # Get the total number of channels and websites
                    total_channels = len(link_manager.get_channels())
                    total_websites = len(link_manager.get_websites())
                    
                    # Adjust max_channels based on total channels - use all channels for manual check
                    max_channels = total_channels  # Process all channels in one batch for manual check
                    logger.info(f"Processing all {total_channels} channels and {total_websites} websites in manual check")
                    
                    # Run the check for channels in the background thread
                    logger.info("Starting background link check for channels")
                    channel_result = check_channels_for_links(bot, link_manager, max_channels)
                    
                    # Run the check for websites
                    logger.info("Starting background link check for websites")
                    website_result = check_websites_for_links(link_manager)
                    
                    # Total result from both checks
                    result = channel_result + website_result
                    
                    logger.info(f"Background check complete. Found {result} new links ({channel_result} from channels, {website_result} from websites).")
                    
                    # Save the check result for later retrieval
                    with lock:
                        last_check_result.update({
                            'timestamp': datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                            'new_links': result,
                            'status': 'completed',
                            'total_channels': total_channels,
                            'channels_checked': total_channels,  # In manual check we process all channels
                            'total_websites': total_websites,
                            'websites_checked': total_websites,
                            'websites_links': website_result
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
        try:
            from bot import setup_bot
            # Initialize the bot with our link manager
            bot_instance = setup_bot(link_manager) 
            if bot_instance:
                bot_status = "Running"
                
                # Start polling for direct messages
                logger.critical("[DIRECT_API_DEBUG] Starting direct API polling for private messages")
                bot_instance.start_polling()
                logger.critical("[DIRECT_API_DEBUG] Direct API polling started successfully")
                
                # Initialize the scheduler
                init_scheduler()
                
                # Initialize the account scheduler for Telegram user accounts
                # Import the necessary scheduler module and set up
                try:
                    from scheduler import SimpleScheduler
                    import logging
                    
                    # Add console handler for immediate output
                    console_handler = logging.StreamHandler()
                    console_handler.setLevel(logging.CRITICAL)
                    logging.getLogger().addHandler(console_handler)
                    
                    logging.critical("[MANUAL_DEBUG] Creating account scheduler...")
                    account_scheduler = SimpleScheduler()
                    
                    logging.critical("[MANUAL_DEBUG] Setting up account scheduler...")
                    setup_account_scheduler(account_scheduler)
                    
                    logging.critical("[MANUAL_DEBUG] Account scheduler initialized successfully")
                except Exception as e:
                    import traceback
                    logging.critical(f"[MANUAL_DEBUG] Error initializing account scheduler: {str(e)}")
                    logging.critical(f"[MANUAL_DEBUG] {traceback.format_exc()}")
        except Exception as e:
            logger.error(f"Failed to auto-initialize bot: {str(e)}")
            logger.error(f"Exception type: {type(e)}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
        
    app.run(host="0.0.0.0", port=5000, debug=True)
