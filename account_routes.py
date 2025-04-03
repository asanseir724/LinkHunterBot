import os
import json
import asyncio  # Keep this for now to avoid breaking existing code
from datetime import datetime
from flask import Blueprint, render_template, request, redirect, url_for, flash, jsonify, session
from user_accounts import AccountManager, UserAccount
from async_helper import run_async, safe_run_coroutine
from avalai_api import avalai_client
from perplexity_api import perplexity_client

# Create blueprint
accounts_bp = Blueprint('accounts', __name__)

# Initialize account manager
account_manager = AccountManager()

@accounts_bp.route('/accounts')
def accounts():
    """View and manage Telegram user accounts"""
    return render_template('accounts.html', 
                           accounts=account_manager.get_all_accounts(),
                           active_accounts=len(account_manager.get_active_accounts()))

@accounts_bp.route('/add_account', methods=['POST'])
def add_account():
    """Add a new Telegram user account"""
    try:
        phone = request.form.get('phone')
        api_id = request.form.get('api_id', '2040')  # Default API ID if not provided
        api_hash = request.form.get('api_hash', 'b18441a1ff607e10a989891a5462e627')  # Default API Hash if not provided
        name = request.form.get('account_name')
        
        if not phone:
            flash("لطفا شماره تلفن را وارد کنید", "danger")
            return redirect(url_for('accounts.accounts'))
        
        # Add the account
        success, message = account_manager.add_account(phone, api_id, api_hash, name)
        if success:
            flash("اکانت با موفقیت اضافه شد. برای اتصال روی دکمه اتصال کلیک کنید.", "success")
        else:
            flash(f"خطا در افزودن اکانت: {message}", "danger")
            
        return redirect(url_for('accounts.accounts'))
    
    except Exception as e:
        flash(f"خطا در افزودن اکانت: {str(e)}", "danger")
        return redirect(url_for('accounts.accounts'))

@accounts_bp.route('/remove_account', methods=['POST'])
def remove_account():
    """Remove a Telegram user account"""
    try:
        phone = request.form.get('phone')
        
        if not phone:
            flash("شماره تلفن نامعتبر", "danger")
            return redirect(url_for('accounts.accounts'))
        
        # Get the account
        account = account_manager.get_account(phone)
        if not account:
            flash("اکانت یافت نشد", "danger")
            return redirect(url_for('accounts.accounts'))
            
        # Disconnect if connected
        if account.connected:
            # Use our helper to safely run the coroutine
            safe_run_coroutine(account.disconnect())
            
        # Remove the account
        success, message = account_manager.remove_account(phone)
        if success:
            flash("اکانت با موفقیت حذف شد", "success")
        else:
            flash(f"خطا در حذف اکانت: {message}", "danger")
            
        return redirect(url_for('accounts.accounts'))
    
    except Exception as e:
        flash(f"خطا در حذف اکانت: {str(e)}", "danger")
        return redirect(url_for('accounts.accounts'))

@accounts_bp.route('/connect_account', methods=['POST'])
def connect_account():
    """Connect a Telegram user account"""
    try:
        data = request.json
        phone = data.get('phone')
        
        if not phone:
            return jsonify({"success": False, "message": "شماره تلفن نامعتبر"})
        
        # Get the account
        account = account_manager.get_account(phone)
        if not account:
            return jsonify({"success": False, "message": "اکانت یافت نشد"})
        
        # Improve logging with consistent logger
        from logger import logger
        
        # Log initial account state
        logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Starting connection for account {phone}, current state: connected={account.connected}, status={account.status}")
            
        # اول اتصال قبلی را قطع کنیم تا کاملا مطمئن شویم
        logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Disconnecting any existing connections for {phone}")
        disconnect_result, disconnect_message = safe_run_coroutine(account.disconnect(), (False, "خطا در قطع اتصال قبلی"))
        logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Disconnect result: {disconnect_result}, message: {disconnect_message}")
        
        # Connect the account using the singleton event loop helper
        logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Trying to connect account {phone}...")
        
        try:
            success, message = safe_run_coroutine(account.connect(), (False, "خطا در اتصال"))
            logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Connection result: success={success}, message={message}")
            
            # Save account manager data
            logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Saving account manager data after connection attempt")
            account_manager.save_accounts()
            
            if success:
                logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Successfully connected account {phone}. Now checking for event handlers...")
                # Wait a bit for event handlers to register completely
                import time
                time.sleep(1)
                
                # Verify connection with extra check
                handlers_info = safe_run_coroutine(account.check_handlers(), {"success": False, "handlers": 0, "message": "Failed to check handlers"})
                logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Handlers check for {phone}: {handlers_info}")
                
                # Final state check
                logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Final state for {phone}: connected={account.connected}, status={account.status}")
            else:
                logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Failed to connect account {phone}: {message}")
                # Additional debug info about the account
                logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Account details: status={account.status}, error={account.error}")
            
            return jsonify({
                "success": success, 
                "message": message, 
                "status": account.status,
                "phone": phone,
                "connection_state": "active" if account.connected else "inactive"
            })
            
        except Exception as e:
            import traceback
            logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Exception during connection attempt for {phone}: {str(e)}")
            logger.critical(f"[CONNECT_ACCOUNT_DEBUG] Traceback: {traceback.format_exc()}")
            
            # Save account manager data even after error
            account_manager.save_accounts()
            
            return jsonify({
                "success": False, 
                "message": f"خطا در اتصال اکانت: {str(e)}",
                "status": account.status,
                "error_details": str(e)
            })
    
    except Exception as e:
        import traceback
        import logging
        logging.critical(f"[CONNECT_ACCOUNT_DEBUG] Error connecting account: {str(e)}")
        logging.critical(f"[CONNECT_ACCOUNT_DEBUG] Traceback: {traceback.format_exc()}")
        return jsonify({"success": False, "message": f"خطا در اتصال اکانت: {str(e)}"})

@accounts_bp.route('/disconnect_account', methods=['POST'])
def disconnect_account():
    """Disconnect a Telegram user account"""
    try:
        data = request.json
        phone = data.get('phone')
        
        if not phone:
            return jsonify({"success": False, "message": "شماره تلفن نامعتبر"})
        
        # Get the account
        account = account_manager.get_account(phone)
        if not account:
            return jsonify({"success": False, "message": "اکانت یافت نشد"})
            
        # Disconnect the account using the singleton event loop helper
        safe_run_coroutine(account.disconnect())
        
        # Save account manager data
        account_manager.save_accounts()
        
        return jsonify({"success": True, "message": "اکانت با موفقیت قطع شد"})
    
    except Exception as e:
        return jsonify({"success": False, "message": f"خطا در قطع اکانت: {str(e)}"})
        
@accounts_bp.route('/connect-all-accounts', methods=['POST'])
def connect_all_accounts():
    """Connect all Telegram user accounts in active status but not connected"""
    from logger import get_logger
    logger = get_logger("account_routes")
    
    logger.info("Connecting all accounts via web interface")
    
    try:
        # Call the connect_all_accounts method
        results = account_manager.connect_all_accounts()
        
        # Log the results
        logger.info(f"Connect all accounts results: {results}")
        
        # Create a user-friendly message
        if results["connection_attempts"] == 0:
            if results["already_connected"] > 0:
                message = f"همه اکانت‌ها ({results['already_connected']}/{results['total_accounts']}) از قبل متصل هستند."
                flash(message, 'info')
            else:
                flash('هیچ اکانتی برای اتصال وجود ندارد.', 'info')
        else:
            message = f"اتصال {results['connection_success']} اکانت از {results['connection_attempts']} انجام شد."
            
            if results["connection_success"] > 0:
                flash(message, 'success')
            else:
                flash(message, 'warning')
                
        return redirect(url_for('accounts.accounts'))
    
    except Exception as e:
        logger.error(f"Error in connect_all_accounts: {str(e)}")
        flash(f"خطا در اتصال اکانت‌ها: {str(e)}", 'danger')
        return redirect(url_for('accounts.accounts'))

@accounts_bp.route('/verify_code', methods=['POST'])
def verify_code():
    """Verify authentication code for a Telegram user account"""
    try:
        # Import the logger
        from logger import logger
        
        phone = request.form.get('phone')
        code = request.form.get('code')
        password = request.form.get('password')
        
        logger.critical(f"[VERIFY_CODE_DEBUG] Starting verification for account {phone}: code_length={len(code) if code else 0}, password_provided={bool(password)}")
        
        if not phone or not code:
            logger.critical(f"[VERIFY_CODE_DEBUG] Missing required parameters for {phone}: code={bool(code)}")
            flash("لطفا کد تأیید را وارد کنید", "danger")
            return redirect(url_for('accounts.accounts'))
        
        # Get the account
        account = account_manager.get_account(phone)
        if not account:
            logger.critical(f"[VERIFY_CODE_DEBUG] Account not found: {phone}")
            flash("اکانت یافت نشد", "danger")
            return redirect(url_for('accounts.accounts'))
            
        # Log current account state
        logger.critical(f"[VERIFY_CODE_DEBUG] Account state before verification: phone={phone}, status={account.status}, connected={account.connected}")
        
        # Sign in with the code using the singleton event loop helper
        logger.critical(f"[VERIFY_CODE_DEBUG] Calling sign_in_with_code for {phone}")
        success, message = safe_run_coroutine(account.sign_in_with_code(code, password), (False, "خطا در تایید کد"))
        logger.critical(f"[VERIFY_CODE_DEBUG] Verification result for {phone}: success={success}, message={message}")
        
        # Save account manager data
        logger.critical(f"[VERIFY_CODE_DEBUG] Saving account data after verification for {phone}")
        account_manager.save_accounts()
        
        # Log final account state
        logger.critical(f"[VERIFY_CODE_DEBUG] Account state after verification: phone={phone}, status={account.status}, connected={account.connected}")
        
        if success:
            logger.critical(f"[VERIFY_CODE_DEBUG] Verification successful for {phone}")
            flash("اکانت با موفقیت تأیید شد", "success")
        else:
            if account.status == "2fa_required":
                logger.critical(f"[VERIFY_CODE_DEBUG] 2FA required for {phone}")
                flash("لطفا رمز دو مرحله‌ای را وارد کنید", "warning")
            else:
                logger.critical(f"[VERIFY_CODE_DEBUG] Verification failed for {phone}: {message}")
                flash(f"خطا در تأیید اکانت: {message}", "danger")
            
        return redirect(url_for('accounts.accounts'))
    
    except Exception as e:
        # Import the logger if not already imported
        from logger import logger
        import traceback
        logger.critical(f"[VERIFY_CODE_DEBUG] Exception during verification: {str(e)}")
        logger.critical(f"[VERIFY_CODE_DEBUG] Traceback: {traceback.format_exc()}")
        flash(f"خطا در تأیید اکانت: {str(e)}", "danger")
        return redirect(url_for('accounts.accounts'))

@accounts_bp.route('/verify_2fa', methods=['POST'])
def verify_2fa():
    """Verify two-factor authentication for a Telegram user account"""
    try:
        phone = request.form.get('phone')
        password = request.form.get('password')
        code = request.form.get('code', '')  # Also get the code if it was submitted
        
        if not phone or not password:
            flash("لطفا رمز دو مرحله‌ای را وارد کنید", "danger")
            return redirect(url_for('accounts.accounts'))
        
        # Get the account
        account = account_manager.get_account(phone)
        if not account:
            flash("اکانت یافت نشد", "danger")
            return redirect(url_for('accounts.accounts'))
        
        # Try to sign in again with the code and password
        success, message = safe_run_coroutine(
            account.sign_in_with_code(code, password),
            (False, "خطا در تایید رمز دو مرحله‌ای")
        )
        
        # Save account manager data
        account_manager.save_accounts()
        
        if success:
            flash("اکانت با موفقیت تأیید شد", "success")
        else:
            flash(f"خطا در تأیید رمز دو مرحله‌ای: {message}", "danger")
            
        return redirect(url_for('accounts.accounts'))
    
    except Exception as e:
        flash(f"خطا در تأیید رمز دو مرحله‌ای: {str(e)}", "danger")
        return redirect(url_for('accounts.accounts'))

@accounts_bp.route('/check_accounts_for_links', methods=['POST'])
def check_accounts_for_links():
    """Check all connected accounts for links"""
    try:
        from link_manager import link_manager  # Import here to avoid circular imports
        from logger import logger
        
        logger.critical("[CHECK_ACCOUNTS_DEBUG] Starting manual check for links in all accounts")
        
        # Get max messages count from settings or use default
        max_messages = 100  # Default, could be from settings
        
        # Make sure all accounts are connected before checking
        logger.critical("[CHECK_ACCOUNTS_DEBUG] Connecting all accounts before checking for links")
        account_manager.connect_all_accounts()
        
        # Log accounts state before checking
        active_accounts = account_manager.get_active_accounts()
        all_accounts = account_manager.get_all_accounts()
        logger.critical(f"[CHECK_ACCOUNTS_DEBUG] Found {len(active_accounts)}/{len(all_accounts)} active accounts before checking")
        
        # Check all accounts for links using the singleton event loop helper
        logger.critical("[CHECK_ACCOUNTS_DEBUG] Calling check_all_accounts_for_links")
        results = safe_run_coroutine(
            account_manager.check_all_accounts_for_links(link_manager, max_messages),
            {
                "success": False,
                "error": "خطا در بررسی اکانت‌ها",
                "total_new_links": 0,
                "accounts_checked": 0,
                "accounts_with_links": 0,
                "account_results": {}
            }
        )
        
        # Log the results
        logger.critical(f"[CHECK_ACCOUNTS_DEBUG] Check completed with results: success={results.get('success')}, accounts_checked={results.get('accounts_checked')}, total_new_links={results.get('total_new_links')}")
        
        # Check if any reconnection attempts were made
        if 'reconnection_attempts' in results:
            logger.critical(f"[CHECK_ACCOUNTS_DEBUG] Reconnection attempts: {results.get('reconnection_attempts')}, success: {results.get('reconnection_success')}")
        
        # Log account states after checking
        active_accounts_after = account_manager.get_active_accounts()
        logger.critical(f"[CHECK_ACCOUNTS_DEBUG] Active accounts after checking: {len(active_accounts_after)}/{len(all_accounts)}")
        
        for phone, account in account_manager.accounts.items():
            logger.critical(f"[CHECK_ACCOUNTS_DEBUG] Account {phone} state after checking: connected={account.connected}, status={account.status}")
        
        return jsonify(results)
    
    except Exception as e:
        from logger import logger
        import traceback
        logger.critical(f"[CHECK_ACCOUNTS_DEBUG] Exception during account check: {str(e)}")
        logger.critical(f"[CHECK_ACCOUNTS_DEBUG] Traceback: {traceback.format_exc()}")
        
        return jsonify({
            "success": False,
            "error": str(e),
            "total_new_links": 0,
            "accounts_checked": 0,
            "accounts_with_links": 0,
            "account_results": {}
        })

@accounts_bp.route('/private_messages')
def private_messages():
    """View private messages received by Telegram user accounts"""
    try:
        # Get chat history from Avalai client
        chat_history = avalai_client.get_chat_history(limit=500)
        
        # Sort by timestamp in descending order (newest first)
        chat_history.sort(key=lambda x: x.get('timestamp', ''), reverse=True)
        
        # Get active accounts for display in sidebar
        accounts = account_manager.get_all_accounts()
        active_accounts = account_manager.get_active_accounts()
        
        # Get Avalai settings
        avalai_settings = avalai_client.get_settings()
        
        return render_template('private_messages.html',
                               chat_history=chat_history,
                               accounts=accounts,
                               active_accounts=active_accounts,
                               avalai_settings=avalai_settings,
                               avalai_enabled=avalai_client.is_enabled())
    except Exception as e:
        flash(f"خطا در بارگیری پیام‌های خصوصی: {str(e)}", "danger")
        return redirect(url_for('accounts.accounts'))
        
@accounts_bp.route('/telegram_desktop')
def telegram_desktop():
    """View private messages with a Telegram Desktop style interface"""
    try:
        # Get the current AI source from session (default to avalai)
        ai_source = session.get('ai_source', 'avalai')
        
        # Get all connected accounts
        accounts = account_manager.get_all_accounts()
        active_accounts = account_manager.get_active_accounts()
        
        # Get chat history from the selected AI client
        if ai_source == 'perplexity':
            chat_history = perplexity_client.get_chat_history(limit=500)
            ai_settings = perplexity_client.get_settings()
            ai_enabled = perplexity_client.is_enabled()
        else:
            chat_history = avalai_client.get_chat_history(limit=500)
            ai_settings = avalai_client.get_settings()
            ai_enabled = avalai_client.is_enabled()
        
        # Sort by timestamp in descending order (newest first)
        chat_history.sort(key=lambda x: x.get('timestamp', ''), reverse=True)
        
        return render_template('telegram_desktop.html',
                               chat_history=chat_history,
                               accounts=accounts,
                               active_accounts=active_accounts,
                               avalai_settings=avalai_client.get_settings(),
                               perplexity_settings=perplexity_client.get_settings(),
                               avalai_enabled=avalai_client.is_enabled(),
                               perplexity_enabled=perplexity_client.is_enabled(),
                               ai_source=ai_source)
    except Exception as e:
        flash(f"خطا در بارگیری پیام‌های خصوصی: {str(e)}", "danger")
        return redirect(url_for('index'))
        
@accounts_bp.route('/add_sample_messages', methods=['POST'])
def add_sample_messages():
    """Add sample messages for testing the UI"""
    try:
        count = int(request.form.get('count', 10))
        if count < 1:
            count = 10
        elif count > 50:
            count = 50  # Limit max to 50
            
        # Get the source from the form or default to 'avalai'
        source = request.form.get('source', 'avalai')
        
        if source == 'perplexity':
            success = perplexity_client.add_sample_messages(count)
            
            if success:
                flash(f"{count} پیام نمونه با موفقیت به Perplexity اضافه شد", "success")
            else:
                flash("خطا در اضافه کردن پیام‌های نمونه به Perplexity", "danger")
                
            return redirect(url_for('perplexity_settings'))
        else:
            success = avalai_client.add_sample_messages(count)
            
            if success:
                flash(f"{count} پیام نمونه با موفقیت اضافه شد", "success")
            else:
                flash("خطا در اضافه کردن پیام‌های نمونه", "danger")
                
            return redirect(url_for('accounts.telegram_desktop'))
    except Exception as e:
        flash(f"خطا در اضافه کردن پیام‌های نمونه: {str(e)}", "danger")
        return redirect(url_for('accounts.telegram_desktop'))

@accounts_bp.route('/set_ai_source', methods=['POST'])
def set_ai_source():
    """Set the AI source for chat responses"""
    try:
        ai_source = request.form.get('ai_source', 'avalai')
        
        # Save the choice in a session variable
        session['ai_source'] = ai_source
        
        flash(f"سرویس هوش مصنوعی به {ai_source} تغییر یافت", "success")
        return redirect(url_for('accounts.telegram_desktop'))
    except Exception as e:
        flash(f"خطا در تغییر سرویس هوش مصنوعی: {str(e)}", "danger")
        return redirect(url_for('accounts.telegram_desktop'))

@accounts_bp.route('/account_messages/<phone>')
def account_messages(phone):
    """View messages for a specific account with phone number"""
    try:
        # Get the account by phone
        account = account_manager.get_account(phone)
        if not account:
            flash("اکانت مورد نظر یافت نشد", "danger")
            return redirect(url_for('accounts.telegram_desktop'))
            
        # برقراری اتصال اتوماتیک اگر اکانت متصل نیست
        if not account.connected or account.status != "active":
            # قبل از redirect، یک بار تلاش می‌کنیم اکانت را متصل کنیم
            from logger import get_logger
            logger = get_logger("account_routes")
            logger.critical(f"[DEBUG] Attempting to auto-connect account {phone} with status {account.status} and connected={account.connected}")
            
            # تلاش خودکار برای اتصال
            connect_result = safe_run_coroutine(account.connect(), (False, "Error auto-connecting"))
            logger.critical(f"[DEBUG] Auto-connect result for {phone}: {connect_result}")
            
            # ذخیره وضعیت اکانت‌ها بعد از اتصال خودکار
            account_manager.save_accounts()
            
            # بررسی دوباره وضعیت اتصال
            if not account.connected or account.status != "active":
                flash("اکانت مورد نظر متصل نیست. تلاش خودکار برای اتصال نیز ناموفق بود.", "danger")
                return redirect(url_for('accounts.telegram_desktop'))
            
        # Get messages from this account (latest 30 private chats)
        result = safe_run_coroutine(
            account.get_private_messages(30),
            {"success": False, "error": "خطا در دریافت پیام‌ها", "messages": []}
        )
        
        if not result.get("success", False):
            flash(f"خطا در دریافت پیام‌های خصوصی: {result.get('error', 'خطای نامشخص')}", "danger")
            return redirect(url_for('accounts.telegram_desktop'))
            
        messages = result.get("messages", [])
        
        # Get all accounts for sidebar
        accounts = account_manager.get_all_accounts()
        active_accounts = account_manager.get_active_accounts()
        
        return render_template('account_messages.html',
                              account=account,
                              messages=messages,
                              accounts=accounts,
                              active_accounts=active_accounts)
    except Exception as e:
        flash(f"خطا در دریافت پیام‌های خصوصی: {str(e)}", "danger")
        return redirect(url_for('accounts.telegram_desktop'))
        
@accounts_bp.route('/clear_chat_history', methods=['POST'])
def clear_chat_history():
    """Clear chat history for all or specific user"""
    try:
        user_id = request.form.get('user_id')
        source = request.form.get('source', 'avalai')
        from_settings = request.form.get('from_settings', False)
        
        if source == 'perplexity':
            # Clear chat history using Perplexity client
            success = perplexity_client.clear_chat_history(user_id)
            
            if success:
                flash("تاریخچه چت Perplexity با موفقیت پاک شد", "success")
            else:
                flash("خطا در پاک کردن تاریخچه چت Perplexity", "danger")
                
            if from_settings:
                return redirect(url_for('perplexity_settings'))
            else:
                return redirect(url_for('accounts.telegram_desktop'))
        else:
            # Clear chat history using Avalai client
            success = avalai_client.clear_chat_history(user_id)
            
            if success:
                flash("تاریخچه چت با موفقیت پاک شد", "success")
            else:
                flash("خطا در پاک کردن تاریخچه چت", "danger")
                
            if from_settings:
                return redirect(url_for('avalai_settings'))
            else:
                return redirect(url_for('accounts.telegram_desktop'))
    except Exception as e:
        flash(f"خطا در پاک کردن تاریخچه چت: {str(e)}", "danger")
        return redirect(url_for('accounts.telegram_desktop'))

# Add a scheduled task to check accounts periodically
def setup_account_scheduler(scheduler):
    """Configure scheduler for automatic account checking"""
    from link_manager import link_manager  # Import here to avoid circular imports
    import logging
    
    logger = logging.getLogger("account_scheduler")
    logger.critical("[ACCOUNT_SCHEDULER_DEBUG] Setting up account scheduler with auto-connect")
    
    # First, try to connect all accounts automatically on startup
    try:
        active_accounts = account_manager.get_active_accounts()
        all_accounts = account_manager.get_all_accounts()
        
        logger.critical(f"[ACCOUNT_SCHEDULER_DEBUG] Found {len(active_accounts)} active accounts and {len(all_accounts)} total accounts")
        
        # If we have any accounts that aren't connected, try to connect them
        if len(all_accounts) > len(active_accounts):
            logger.critical("[ACCOUNT_SCHEDULER_DEBUG] Auto-connecting accounts on startup...")
            
            # For each account that needs connection
            for account in all_accounts:
                if not account.connected and account.status != "active":
                    logger.critical(f"[ACCOUNT_SCHEDULER_DEBUG] Auto-connecting account: {account.phone}")
                    
                    # Try to connect the account
                    connect_result = safe_run_coroutine(account.connect(), (False, "Error connecting"))
                    logger.critical(f"[ACCOUNT_SCHEDULER_DEBUG] Connect result for {account.phone}: {connect_result}")
                    
                    if account.connected:
                        # Check for registered event handlers
                        handlers_result = safe_run_coroutine(account.check_handlers(), {"success": False, "handlers": 0})
                        logger.critical(f"[ACCOUNT_SCHEDULER_DEBUG] Handlers check for {account.phone}: {handlers_result}")
            
            # Save account states
            account_manager.save_accounts()
            logger.critical("[ACCOUNT_SCHEDULER_DEBUG] Saved account states after auto-connect")
    except Exception as e:
        logger.critical(f"[ACCOUNT_SCHEDULER_DEBUG] Error during auto-connect: {str(e)}")
        import traceback
        logger.critical(f"[ACCOUNT_SCHEDULER_DEBUG] Traceback: {traceback.format_exc()}")
    
    # Define the check function for periodic checks
    def check_accounts_job():
        """Job function to check all accounts for links"""
        try:
            if not account_manager.get_active_accounts():
                # No active accounts, skip check
                return
                
            # Log the check
            from logger import get_logger
            logger = get_logger("account_checker")
            logger.info(f"Starting scheduled check for {len(account_manager.get_active_accounts())} active accounts")
            
            # Run the check using the singleton event loop helper
            results = safe_run_coroutine(
                account_manager.check_all_accounts_for_links(link_manager, 100),
                {
                    "success": False,
                    "error": "خطا در بررسی اکانت‌ها",
                    "total_new_links": 0,
                    "accounts_checked": 0,
                    "accounts_with_links": 0,
                    "account_results": {}
                }
            )
            
            # Log the results
            logger.info(f"Scheduled check complete. Found {results['total_new_links']} new links from {results['accounts_with_links']} accounts")
            
            # Update the global last_check_result in main module to include user account stats
            try:
                import sys
                if 'main' in sys.modules:
                    main_module = sys.modules['main']
                    if hasattr(main_module, 'last_check_result') and hasattr(main_module, 'lock'):
                        with main_module.lock:
                            main_module.last_check_result.update({
                                'user_groups_checked': results.get('groups_checked', 0),
                                'user_accounts_checked': results.get('accounts_checked', 0)
                            })
                        logger.info(f"Updated main check stats with user account info: {results.get('groups_checked', 0)} groups from {results.get('accounts_checked', 0)} accounts")
                    else:
                        logger.warning("Could not update main check stats: last_check_result or lock not found")
                else:
                    logger.warning("Could not update main check stats: main module not found")
            except Exception as e:
                logger.error(f"Error updating main check stats: {str(e)}")
        except Exception as e:
            from logger import get_logger
            logger = get_logger("account_checker")
            logger.error(f"Error in scheduled account check: {str(e)}")
    
    # Add the job to run every 1 minute (can be configurable)
    scheduler.add_job(check_accounts_job, 'interval', minutes=1, id='check_accounts_job')