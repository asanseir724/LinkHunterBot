import os
import re
import asyncio
from telegram import Update
from telegram.ext import Application, CommandHandler, CallbackContext, ContextTypes
from logger import get_logger

# Get application logger
logger = get_logger(__name__)

def setup_bot(link_manager):
    """Set up and configure the Telegram bot"""
    
    # Get token from environment variable
    token = os.environ.get("TELEGRAM_BOT_TOKEN")
    if not token:
        raise ValueError("TELEGRAM_BOT_TOKEN environment variable not set")
    
    # Create the Application and pass it the bot's token
    application = Application.builder().token(token).build()
    
    # Define command handlers
    async def start_command(update, context: ContextTypes.DEFAULT_TYPE):
        """Send a message when the command /start is issued."""
        user = update.effective_user
        await update.message.reply_text(
            f"Hi {user.first_name}! I'm a link collector bot. "
            f"I can help you collect unique Telegram group links from channels.\n"
            f"Use /help to see available commands."
        )
    
    async def help_command(update: Update, context: CallbackContext):
        """Send a message when the command /help is issued."""
        help_text = (
            "Available commands:\n"
            "/start - Start the bot\n"
            "/help - Show this help message\n"
            "/add_channel [channel] - Add a channel to monitor\n"
            "/list_channels - List monitored channels\n"
            "/remove_channel [channel] - Remove a channel from monitoring\n"
            "/get_links [count] - Get the latest unique links (default: 10)\n"
            "/set_interval [minutes] - Set check interval in minutes\n"
            "/check_now - Check for new links now\n"
            "/status - Show bot status"
        )
        await update.message.reply_text(help_text)
    
    async def add_channel_command(update: Update, context: CallbackContext):
        """Add a channel to the monitoring list"""
        if not context.args:
            await update.message.reply_text("Please provide a channel username or ID.")
            return
        
        channel = context.args[0]
        if channel.startswith('@'):
            channel = channel[1:]  # Remove @ if present
            
        if link_manager.add_channel(channel):
            await update.message.reply_text(f"Channel '{channel}' added to monitoring list.")
        else:
            await update.message.reply_text(f"Channel '{channel}' is already being monitored.")
    
    async def list_channels_command(update: Update, context: CallbackContext):
        """List all monitored channels"""
        channels = link_manager.get_channels()
        if not channels:
            await update.message.reply_text("No channels are currently being monitored.")
            return
            
        message = "Monitored channels:\n"
        for i, channel in enumerate(channels, 1):
            message += f"{i}. @{channel}\n"
            
        await update.message.reply_text(message)
    
    async def remove_channel_command(update: Update, context: CallbackContext):
        """Remove a channel from the monitoring list"""
        if not context.args:
            await update.message.reply_text("Please provide a channel username or ID to remove.")
            return
            
        channel = context.args[0]
        if channel.startswith('@'):
            channel = channel[1:]  # Remove @ if present
            
        if link_manager.remove_channel(channel):
            await update.message.reply_text(f"Channel '{channel}' removed from monitoring list.")
        else:
            await update.message.reply_text(f"Channel '{channel}' is not in the monitoring list.")
    
    async def get_links_command(update: Update, context: CallbackContext):
        """Get the latest unique links"""
        try:
            count = 10  # Default
            if context.args:
                count = int(context.args[0])
                if count < 1:
                    await update.message.reply_text("Please provide a positive number.")
                    return
        except ValueError:
            await update.message.reply_text("Please provide a valid number.")
            return
            
        links = link_manager.get_all_links()
        
        if not links:
            await update.message.reply_text("No links have been collected yet.")
            return
            
        # Get the most recent links, limited by count
        latest_links = links[-count:] if len(links) > count else links
            
        message = f"Latest {len(latest_links)} unique links:\n\n"
        for i, link in enumerate(latest_links, 1):
            message += f"{i}. {link}\n"
            
            # Telegram has message length limits, split if necessary
            if len(message) > 3500:
                await update.message.reply_text(message)
                message = ""
                
        if message:
            await update.message.reply_text(message)
    
    async def set_interval_command(update: Update, context: CallbackContext):
        """Set the check interval in minutes"""
        if not context.args:
            await update.message.reply_text("Please provide an interval in minutes.")
            return
            
        try:
            interval = int(context.args[0])
            if interval < 1:
                await update.message.reply_text("Interval must be at least 1 minute.")
                return
                
            link_manager.set_check_interval(interval)
            # Scheduler update is handled in app.py
            
            await update.message.reply_text(f"Check interval set to {interval} minutes.")
        except ValueError:
            await update.message.reply_text("Please provide a valid number for the interval.")
    
    async def check_now_command(update: Update, context: CallbackContext):
        """Manually trigger a link check"""
        await update.message.reply_text("Checking for new links now...")
        
        # The actual check is triggered by app.py
        context.application.create_task(check_channels_for_links(context.bot, link_manager))
    
    async def status_command(update: Update, context: CallbackContext):
        """Show bot status"""
        channels_count = len(link_manager.get_channels())
        links_count = len(link_manager.get_all_links())
        interval = link_manager.get_check_interval()
        last_check = link_manager.get_last_check_time() or "Never"
        
        status = (
            f"Bot Status:\n"
            f"- Monitored channels: {channels_count}\n"
            f"- Collected links: {links_count}\n"
            f"- Check interval: {interval} minutes\n"
            f"- Last check: {last_check}\n"
        )
        
        await update.message.reply_text(status)
    
    # Register handlers
    application.add_handler(CommandHandler("start", start_command))
    application.add_handler(CommandHandler("help", help_command))
    application.add_handler(CommandHandler("add_channel", add_channel_command))
    application.add_handler(CommandHandler("list_channels", list_channels_command))
    application.add_handler(CommandHandler("remove_channel", remove_channel_command))
    application.add_handler(CommandHandler("get_links", get_links_command))
    application.add_handler(CommandHandler("set_interval", set_interval_command))
    application.add_handler(CommandHandler("check_now", check_now_command))
    application.add_handler(CommandHandler("status", status_command))
    
    # Start the bot
    application.run_polling(allowed_updates=Update.ALL_TYPES)
    
    return application

async def check_channels_for_links(bot, link_manager):
    """Check monitored channels for new links"""
    channels = link_manager.get_channels()
    total_new_links = 0
    channel_new_links = {}  # Track new links per channel
    
    if not channels:
        logger.info("No channels to check")
        return 0
    
    logger.info(f"Starting check for {len(channels)} channels")
    
    for channel in channels:
        try:
            logger.info(f"Checking channel: {channel}")
            channel_new_links[channel] = 0
            
            # We'll try to get the latest messages from the channel
            # This requires the bot to be a member of the channel
            chat_id = f"@{channel}"
            
            try:
                # Get channel information to verify bot access
                chat = await bot.get_chat(chat_id)
                logger.info(f"Connected to channel: {chat.title} ({chat_id})")
                
                # Try to get the chat history
                logger.debug(f"Attempting to get history for {chat_id}")
                
                # Get the last 100 messages using get_updates
                # This is a simplified approach - in production we should use getChatHistory
                try:
                    # First check if the bot has the necessary permissions
                    member = await bot.get_chat_member(chat_id=chat_id, user_id=bot.id)
                    logger.debug(f"Bot membership status in channel: {member.status}")
                    
                    # Use getUpdates to get recent messages
                    updates = await bot.get_updates(offset=-1, limit=100)
                    logger.debug(f"Retrieved {len(updates)} updates")
                    
                    for update in updates:
                        # Check if the update is from the channel we're monitoring
                        if update.channel_post and update.channel_post.chat.username == channel:
                            logger.debug(f"Found message from channel {channel}")
                            text = update.channel_post.text or update.channel_post.caption or ""
                            
                            # Extract Telegram group/channel links
                            links = []
                            # Look for t.me links
                            t_me_links = re.findall(r'https?://t\.me/[^\s]+', text)
                            if t_me_links:
                                logger.debug(f"Found {len(t_me_links)} t.me links")
                                links.extend(t_me_links)
                            
                            # Also look for telegram.me links
                            telegram_me_links = re.findall(r'https?://telegram\.me/[^\s]+', text)
                            if telegram_me_links:
                                logger.debug(f"Found {len(telegram_me_links)} telegram.me links")
                                links.extend(telegram_me_links)
                            
                            # Add unique links to storage
                            for link in links:
                                logger.debug(f"Processing link: {link}")
                                if link_manager.add_link(link):
                                    logger.info(f"Added new link: {link}")
                                    total_new_links += 1
                                    channel_new_links[channel] += 1
                                else:
                                    logger.debug(f"Link already exists: {link}")
                
                except Exception as e:
                    logger.error(f"Error getting messages from {chat_id}: {str(e)}")
                    logger.error(f"Exception type: {type(e)}")
                    import traceback
                    logger.error(f"Traceback: {traceback.format_exc()}")
            
            except Exception as e:
                logger.error(f"Error accessing channel {chat_id}: {str(e)}")
                logger.error(f"Exception type: {type(e)}")
                import traceback
                logger.error(f"Traceback: {traceback.format_exc()}")
            
            logger.info(f"Found {channel_new_links[channel]} new links in {channel}")
            
        except Exception as e:
            logger.error(f"Error checking channel {channel}: {str(e)}")
            logger.error(f"Exception type: {type(e)}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
    
    # Update last check time
    link_manager.update_last_check_time()
    
    # Log summary
    logger.info(f"Total new links found: {total_new_links}")
    for channel, count in channel_new_links.items():
        logger.info(f"Channel {channel}: {count} new links")
    
    return total_new_links
