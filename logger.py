import logging
import os
import json
import datetime
from logging.handlers import RotatingFileHandler

# Create logs directory if it doesn't exist
os.makedirs('logs', exist_ok=True)

# Configure basic logging
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)

# Create file handler for general logs
file_handler = RotatingFileHandler(
    'logs/bot.log',
    maxBytes=10485760,  # 10MB
    backupCount=5
)
file_handler.setFormatter(logging.Formatter(
    '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
))

# Create a separate JSON handler for web display
json_handler = RotatingFileHandler(
    'logs/bot_json.log',
    maxBytes=10485760,  # 10MB
    backupCount=5
)

# Create a special handler just for connection-related logs
connection_handler = RotatingFileHandler(
    'logs/connection.log',
    maxBytes=10485760,  # 10MB
    backupCount=5
)
connection_handler.setFormatter(logging.Formatter(
    '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
))

# Add a filter to only log CONNECTION_DEBUG messages
class ConnectionFilter(logging.Filter):
    def filter(self, record):
        return '[CONNECTION_DEBUG]' in record.getMessage() or \
               '[CONNECT_DEBUG]' in record.getMessage() or \
               '[CONNECT_DEEP_DEBUG]' in record.getMessage() or \
               '[CONNECT_ERROR]' in record.getMessage() or \
               '[CONNECT_ACCOUNT_DEBUG]' in record.getMessage() or \
               '[CONNECT_SUCCESS]' in record.getMessage() or \
               '[VERIFY_CODE_DEBUG]' in record.getMessage() or \
               '[SIGN_IN_DEBUG]' in record.getMessage() or \
               '[CHECK_ACCOUNTS_DEBUG]' in record.getMessage()

connection_handler.addFilter(ConnectionFilter())

# Add handlers to root logger
root_logger = logging.getLogger()
root_logger.addHandler(file_handler)
root_logger.addHandler(connection_handler)

# Custom JSON formatter
class JsonFormatter(logging.Formatter):
    def format(self, record):
        log_record = {
            'timestamp': datetime.datetime.fromtimestamp(record.created).strftime('%Y-%m-%d %H:%M:%S'),
            'module': record.name,
            'level': record.levelname,
            'message': record.getMessage(),
        }
        
        # Add exception info if available
        if record.exc_info:
            log_record['exception'] = self.formatException(record.exc_info)
            
        return json.dumps(log_record)

json_formatter = JsonFormatter()
json_handler.setFormatter(json_formatter)
root_logger.addHandler(json_handler)

def get_logger(name):
    """Get a logger with the given name."""
    return logging.getLogger(name)

def get_all_logs(limit=1000):
    """Get all logs for web display from the JSON log file."""
    logs = []
    try:
        with open('logs/bot_json.log', 'r') as f:
            for line in f:
                try:
                    log = json.loads(line.strip())
                    logs.append(log)
                except json.JSONDecodeError:
                    # Skip invalid lines
                    continue
    except FileNotFoundError:
        # Return empty list if file doesn't exist yet
        pass
    
    # Return the most recent logs up to the limit
    return sorted(logs, key=lambda x: x.get('timestamp', ''), reverse=True)[:limit]

def clear_logs():
    """Clear all logs."""
    try:
        open('logs/bot.log', 'w').close()
        open('logs/bot_json.log', 'w').close()
        open('logs/connection.log', 'w').close()
        open('logs/app.log', 'w').close()
        return True
    except Exception as e:
        logging.error(f"Failed to clear logs: {e}")
        return False

def get_connection_logs(limit=500):
    """Get connection-specific logs for debugging connection issues.
    
    Args:
        limit (int): Maximum number of log entries to return
        
    Returns:
        list: List of connection logs
    """
    logs = []
    try:
        with open('logs/connection.log', 'r') as f:
            for line in f:
                logs.append(line.strip())
    except FileNotFoundError:
        # Return empty list if file doesn't exist yet
        pass
    
    # Return the most recent logs up to the limit
    return logs[-limit:]