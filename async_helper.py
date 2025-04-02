import asyncio
import functools
import logging
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
            logger.debug("Creating new event loop")
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
        loop = get_event_loop()
        return loop.run_until_complete(coro_func(*args, **kwargs))
    
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
        loop = get_event_loop()
        return loop.run_until_complete(coro)
    except Exception as e:
        logger.error(f"Error running coroutine: {str(e)}")
        return default_result