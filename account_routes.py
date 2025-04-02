import os
import json
import asyncio
from datetime import datetime
from flask import Blueprint, render_template, request, redirect, url_for, flash, jsonify
from user_accounts import AccountManager, UserAccount

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
            asyncio.run(account.disconnect())
            
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
            
        # Connect the account
        success, message = asyncio.run(account.connect())
        
        # Save account manager data
        account_manager.save_accounts()
        
        return jsonify({"success": success, "message": message, "status": account.status})
    
    except Exception as e:
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
            
        # Disconnect the account
        asyncio.run(account.disconnect())
        
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
            
        # Sign in with the code
        success, message = asyncio.run(account.sign_in_with_code(code, password))
        
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
        
        if not phone or not password:
            flash("لطفا رمز دو مرحله‌ای را وارد کنید", "danger")
            return redirect(url_for('accounts.accounts'))
        
        # Get the account
        account = account_manager.get_account(phone)
        if not account:
            flash("اکانت یافت نشد", "danger")
            return redirect(url_for('accounts.accounts'))
            
        # Sign in with the 2FA password
        if account.client:
            success, message = asyncio.run(account.client.sign_in(password=password))
            account.status = "active" if success else "2fa_error"
            account.connected = success
            account.error = None if success else message
            
            # Save account manager data
            account_manager.save_accounts()
            
            if success:
                flash("اکانت با موفقیت تأیید شد", "success")
            else:
                flash(f"خطا در تأیید رمز دو مرحله‌ای: {message}", "danger")
        else:
            flash("اکانت به درستی راه‌اندازی نشده است", "danger")
            
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
        
        # Check all accounts for links
        results = asyncio.run(account_manager.check_all_accounts_for_links(link_manager, max_messages))
        
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
            
            # Run the check
            results = asyncio.run(account_manager.check_all_accounts_for_links(link_manager, 100))
            
            # Log the results
            logger.info(f"Scheduled check complete. Found {results['total_new_links']} new links from {results['accounts_with_links']} accounts")
        except Exception as e:
            from logger import get_logger
            logger = get_logger("account_checker")
            logger.error(f"Error in scheduled account check: {str(e)}")
    
    # Add the job to run every 1 minute (can be configurable)
    scheduler.add_job(check_accounts_job, 'interval', minutes=1, id='check_accounts_job')