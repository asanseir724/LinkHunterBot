import threading
import time
from datetime import datetime, timedelta
from logger import get_logger

# Get module logger
logger = get_logger(__name__)

class SimpleScheduler:
    """Simple scheduler for periodic tasks"""
    
    def __init__(self):
        self.running = False
        self.job_thread = None
        self.job_function = None
        self.interval_minutes = 10  # Default interval - run every 10 minutes
        self.next_run_time = None
        
    def start(self):
        """Start the scheduler"""
        if self.running:
            return
        
        self.running = True
        self.job_thread = threading.Thread(target=self._scheduler_loop)
        self.job_thread.daemon = True
        self.job_thread.start()
        logger.info(f"Scheduler started with {self.interval_minutes} minute interval")
        
    def stop(self):
        """Stop the scheduler"""
        self.running = False
        if self.job_thread:
            self.job_thread.join(timeout=1)
            self.job_thread = None
        logger.info("Scheduler stopped")
        
    def _scheduler_loop(self):
        """Main scheduler loop"""
        while self.running:
            if self.job_function:
                try:
                    self._update_next_run_time()
                    self.job_function()
                except Exception as e:
                    logger.error(f"Error in scheduled job: {e}")
                    import traceback
                    logger.error(f"Traceback: {traceback.format_exc()}")
            
            # Sleep for the specified interval
            sleep_seconds = self.interval_minutes * 60
            for _ in range(sleep_seconds):
                if not self.running:
                    break
                time.sleep(1)
    
    def set_job(self, job_function):
        """Set the job function to run"""
        self.job_function = job_function
        
    def update_interval(self, minutes):
        """Update the check interval"""
        self.interval_minutes = minutes
        self._update_next_run_time()
        logger.info(f"Updated scheduler interval to {minutes} minutes")
        
    def run_job_now(self):
        """Run the job immediately"""
        if self.job_function:
            logger.info("Running job immediately")
            try:
                self.job_function()
                self._update_next_run_time()
            except Exception as e:
                logger.error(f"Error running job immediately: {e}")
                import traceback
                logger.error(f"Traceback: {traceback.format_exc()}")
        else:
            logger.warning("No job function set")
            
    def get_next_run_time(self):
        """Get the next scheduled run time"""
        if self.next_run_time:
            return self.next_run_time.strftime("%Y-%m-%d %H:%M:%S")
        return "Not scheduled"
        
    def _update_next_run_time(self):
        """Update the next run time based on current time and interval"""
        self.next_run_time = datetime.now() + timedelta(minutes=self.interval_minutes)


def setup_scheduler(bot, link_manager):
    """Set up scheduler for automatic link checking"""
    scheduler = SimpleScheduler()
    
    def check_for_new_links():
        """Job function to check for new links"""
        try:
            logger.info("Running scheduled link check")
            channels = link_manager.get_channels()
            
            if not channels:
                logger.info("No channels to check")
                return
            
            try:
                # Import the check_channels_for_links function from bot module
                from bot import check_channels_for_links
                logger.info(f"Imported check_channels_for_links function")
                
                # Run the check function directly (it's now synchronous)
                logger.info(f"Checking {len(channels)} channels for new links")
                
                # Adjust max_channels based on total channels
                max_channels = 15  # For scheduled checks, use a smaller batch size
                total_channels = len(channels)
                if total_channels > 50:
                    max_channels = 20
                    logger.info(f"Large number of channels ({total_channels}), using batch size of {max_channels}")
                
                result = check_channels_for_links(bot, link_manager, max_channels)
                logger.info(f"Found {result} new links in scheduled check")
                
            except ImportError as e:
                logger.error(f"Failed to import check_channels_for_links: {e}")
            except Exception as e:
                logger.error(f"Error running check_channels_for_links: {e}")
                import traceback
                logger.error(f"Traceback: {traceback.format_exc()}")
                
                # Fallback - just update the timestamp
                logger.info("Using fallback - just updating last check time")
                link_manager.update_last_check_time()
            
        except Exception as e:
            logger.error(f"Error in scheduled job: {e}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
    
    # Set the job function
    scheduler.set_job(check_for_new_links)
    
    # Set the interval from link manager
    interval = link_manager.get_check_interval()
    scheduler.update_interval(interval)
    
    # Start the scheduler
    scheduler.start()
    
    return scheduler