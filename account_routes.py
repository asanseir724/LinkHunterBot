import os
import json
import asyncio  # Keep this for now to avoid breaking existing code
from datetime import datetime
from flask import Blueprint, render_template, request, redirect, url_for, flash, jsonify
from user_accounts import AccountManager, UserAccount
from async_helper import run_async, safe_run_coroutine
from avalai_api import avalai_client

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
        
        # اول اتصال قبلی را قطع کنیم تا کاملا مطمئن شویم
        safe_run_coroutine(account.disconnect(), (False, "خطا در قطع اتصال قبلی"))
        
        # Connect the account using the singleton event loop helper
        import logging
        logging.critical(f"[CONNECT_ACCOUNT_DEBUG] Trying to connect account {phone}...")
        success, message = safe_run_coroutine(account.connect(), (False, "خطا در اتصال"))
        logging.critical(f"[CONNECT_ACCOUNT_DEBUG] Connection result: success={success}, message={message}")
        
        # Save account manager data
        account_manager.save_accounts()
        
        if success:
            logging.critical("[CONNECT_ACCOUNT_DEBUG] Successfully connected. Now checking for event handlers...")
            # Wait a bit for event handlers to register completely
            import time
            time.sleep(1)
        
        return jsonify({"success": success, "message": message, "status": account.status})
    
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

@accounts_bp.route('/verify_code', methods=['POST'])
def verify_code():
    """Verify authentication code for a Telegram user account"""
    try:
        phone = request.form.get('phone')
        code = request.form.get('code')
        password = request.form.get('password')
        
        if not phone or not code:
            flash("لطفا کد تأیید را وارد کنید", "danger")
            return redirect(url_for('accounts.accounts'))
        
        # Get the account
        account = account_manager.get_account(phone)
        if not account:
            flash("اکانت یافت نشد", "danger")
            return redirect(url_for('accounts.accounts'))
            
        # Sign in with the code using the singleton event loop helper
        success, message = safe_run_coroutine(account.sign_in_with_code(code, password), (False, "خطا در تایید کد"))
        
        # Save account manager data
        account_manager.save_accounts()
        
        if success:
            flash("اکانت با موفقیت تأیید شد", "success")
        else:
            if account.status == "2fa_required":
                flash("لطفا رمز دو مرحله‌ای را وارد کنید", "warning")
            else:
                flash(f"خطا در تأیید اکانت: {message}", "danger")
            
        return redirect(url_for('accounts.accounts'))
    
    except Exception as e:
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
        
        # Get max messages count from settings or use default
        max_messages = 100  # Default, could be from settings
        
        # Check all accounts for links using the singleton event loop helper
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
        
        return jsonify(results)
    
    except Exception as e:
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
        active_accounts = len(account_manager.get_active_accounts())
        
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
        # Get chat history from Avalai client
        chat_history = avalai_client.get_chat_history(limit=500)
        
        # Sort by timestamp in descending order (newest first)
        chat_history.sort(key=lambda x: x.get('timestamp', ''), reverse=True)
        
        # Get Avalai settings
        avalai_settings = avalai_client.get_settings()
        
        return render_template('private_messages.html',
                               chat_history=chat_history, 
                               avalai_settings=avalai_settings,
                               avalai_enabled=avalai_client.is_enabled())
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
            
        success = avalai_client.add_sample_messages(count)
        
        if success:
            flash(f"{count} پیام نمونه با موفقیت اضافه شد", "success")
        else:
            flash("خطا در اضافه کردن پیام‌های نمونه", "danger")
            
        return redirect(url_for('accounts.telegram_desktop'))
    except Exception as e:
        flash(f"خطا در اضافه کردن پیام‌های نمونه: {str(e)}", "danger")
        return redirect(url_for('accounts.telegram_desktop'))

@accounts_bp.route('/clear_chat_history', methods=['POST'])
def clear_chat_history():
    """Clear chat history for all or specific user"""
    try:
        user_id = request.form.get('user_id')
        
        # Clear chat history using Avalai client
        success = avalai_client.clear_chat_history(user_id)
        
        if success:
            flash("تاریخچه چت با موفقیت پاک شد", "success")
        else:
            flash("خطا در پاک کردن تاریخچه چت", "danger")
            
        return redirect(url_for('accounts.private_messages'))
    except Exception as e:
        flash(f"خطا در پاک کردن تاریخچه چت: {str(e)}", "danger")
        return redirect(url_for('accounts.private_messages'))

# Add a scheduled task to check accounts periodically
def setup_account_scheduler(scheduler):
    """Configure scheduler for automatic account checking"""
    from link_manager import link_manager  # Import here to avoid circular imports
    
    # Define the check function
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