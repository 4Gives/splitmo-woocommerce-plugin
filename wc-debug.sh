#!/bin/bash
# Tail Logs from Woocommerce
find /var/www/html/wp-content/uploads/wc-logs/ -type f -name "*.log" -exec tail -f {} +