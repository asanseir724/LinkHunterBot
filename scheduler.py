import asyncio
from apscheduler.schedulers.background import BackgroundScheduler
from apscheduler.triggers.interval import IntervalTrigger
from datetime import datetime, timedelta
from logger import get_logger

# Get module logger
logger = get_logger(__name__)

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
            
            # We need to adapt async function for BackgroundScheduler
            # Create an event loop to run the async function
            try:
                # Create a new event loop
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)
                
                # Import the check_channels_for_links function from bot module
                try:
                    from bot import check_channels_for_links
                    logger.info(f"Imported check_channels_for_links function")
                    
                    # Run the async function in the event loop
                    logger.info(f"Checking {len(channels)} channels for new links")
                    result = loop.run_until_complete(check_channels_for_links(bot, link_manager))
                    logger.info(f"Found {result} new links in scheduled check")
                    
                except ImportError as e:
                    logger.error(f"Failed to import check_channels_for_links: {e}")
                except Exception as e:
                    logger.error(f"Error running check_channels_for_links: {e}")
                    import traceback
                    logger.error(f"Traceback: {traceback.format_exc()}")
                finally:
                    # Close the event loop
                    loop.close()
                    
            except Exception as e:
                logger.error(f"Error with event loop: {e}")
                import traceback
                logger.error(f"Traceback: {traceback.format_exc()}")
                
                # Fallback - just update the timestamp
                logger.info("Using fallback - just updating last check time")
                link_manager.update_last_check_time()
            
        except Exception as e:
            logger.error(f"Error in scheduled job: {e}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
    
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
