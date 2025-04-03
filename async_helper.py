import asyncio
import functools
import logging
import traceback
from threading import Lock

logger = logging.getLogger(__name__)

# Global event loop for asyncio operations
_event_loop = None
_loop_lock = Lock()

def get_event_loop():
    """
    Returns a singleton event loop for all asyncio operations
    
    This ensures we're using the same event loop across the application
    which helps prevent the "event loop changed" errors with Telethon
    """
    global _event_loop
    
    with _loop_lock:
        if _event_loop is None or _event_loop.is_closed():
            logger.critical("[ASYNC_HELPER_DEBUG] Creating new event loop")
            _event_loop = asyncio.new_event_loop()
            asyncio.set_event_loop(_event_loop)
            logger.critical(f"[ASYNC_HELPER_DEBUG] Successfully created new event loop: {_event_loop}")
        
        # Make sure the loop is still running
        if _event_loop.is_closed():
            logger.critical("[ASYNC_HELPER_DEBUG] Event loop was closed! Creating a new one.")
            _event_loop = asyncio.new_event_loop()
            asyncio.set_event_loop(_event_loop)
        
        return _event_loop

def run_async(coro_func):
    """
    Decorator to run an async function in the global event loop
    
    Args:
        coro_func: The coroutine function to run
        
    Returns:
        A wrapper function that runs the coroutine in the global event loop
    """
    @functools.wraps(coro_func)
    def wrapper(*args, **kwargs):
        try:
            loop = get_event_loop()
            logger.critical(f"[ASYNC_HELPER_DEBUG] Running coroutine function {coro_func.__name__} in loop {loop}")
            return loop.run_until_complete(coro_func(*args, **kwargs))
        except Exception as e:
            logger.critical(f"[ASYNC_HELPER_DEBUG] Error in run_async for {coro_func.__name__}: {str(e)}")
            logger.critical(f"[ASYNC_HELPER_DEBUG] Traceback: {traceback.format_exc()}")
            raise
    
    return wrapper

def safe_run_coroutine(coro, default_result=None):
    """
    Safely run a coroutine in the global event loop
    
    Args:
        coro: The coroutine to run
        default_result: The default result to return if an error occurs
        
    Returns:
        The result of the coroutine, or the default result if an error occurs
    """
    try:
        # Get coroutine name if possible for better debugging
        coro_name = getattr(coro, "__qualname__", str(coro))
        
        # Get the event loop
        loop = get_event_loop()
        logger.critical(f"[ASYNC_HELPER_DEBUG] Safely running coroutine {coro_name} in loop {loop}")
        
        # Actually run the coroutine
        result = loop.run_until_complete(coro)
        logger.critical(f"[ASYNC_HELPER_DEBUG] Coroutine {coro_name} completed successfully")
        return result
    except Exception as e:
        logger.critical(f"[ASYNC_HELPER_DEBUG] Error running coroutine: {str(e)}")
        logger.critical(f"[ASYNC_HELPER_DEBUG] Traceback: {traceback.format_exc()}")
        return default_result