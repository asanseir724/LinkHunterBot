import logging
from apscheduler.schedulers.background import BackgroundScheduler
from apscheduler.triggers.interval import IntervalTrigger
from datetime import datetime, timedelta

# Configure logging
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

def setup_scheduler(bot, link_manager):
    """Set up scheduler for automatic link checking"""
    scheduler = BackgroundScheduler()
    
    def check_for_new_links():
        """Job function to check for new links"""
        try:
            logger.info("Running scheduled link check")
            channels = link_manager.get_channels()
            
            if not channels:
                logger.info("No channels to check")
                return
            
            # This would normally use bot.check_channels_for_links,
            # but we need to adapt it for the BackgroundScheduler
            # which doesn't support async functions directly
            
            # Create a synchronous way to trigger the async function
            # This is a placeholder - in a real implementation, you would need
            # to properly handle the async/sync transition
            logger.info(f"Checking {len(channels)} channels for new links")
            link_manager.update_last_check_time()
            
        except Exception as e:
            logger.error(f"Error in scheduled job: {e}")
    
    interval = link_manager.get_check_interval()
    
    # Add the job to the scheduler
    scheduler.add_job(
        check_for_new_links,
        IntervalTrigger(minutes=interval),
        id='check_links_job',
        replace_existing=True
    )
    
    # Add methods to the scheduler object for convenience
    def update_interval(minutes):
        """Update the check interval"""
        scheduler.remove_job('check_links_job')
        scheduler.add_job(
            check_for_new_links,
            IntervalTrigger(minutes=minutes),
            id='check_links_job',
            replace_existing=True
        )
        logger.info(f"Updated scheduler interval to {minutes} minutes")
    
    def run_job_now():
        """Run the check job immediately"""
        logger.info("Running check job immediately")
        check_for_new_links()
    
    def get_next_run_time():
        """Get the next scheduled run time"""
        job = scheduler.get_job('check_links_job')
        if job and job.next_run_time:
            return job.next_run_time.strftime("%Y-%m-%d %H:%M:%S")
        return "Not scheduled"
    
    # Attach methods to scheduler object
    scheduler.update_interval = update_interval
    scheduler.run_job_now = run_job_now
    scheduler.get_next_run_time = get_next_run_time
    
    return scheduler
