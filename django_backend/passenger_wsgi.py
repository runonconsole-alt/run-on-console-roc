import os
import sys

# ============================================================
# Run On Console (ROC) - Phusion Passenger WSGI Entry Point
# HosterPK cPanel Python Application Gateway
# ============================================================

# Add project root directory to python path
cwd = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, cwd)

# Set Django production settings module
os.environ['DJANGO_SETTINGS_MODULE'] = 'roc_backend.settings'

# Import Django WSGI Application Handler
from roc_backend.wsgi import application
