#!/bin/bash
# zips the whole plugin that can be uploaded to wordpress sites
php composer.phar install
zip -r wc-splitmo-payment-gateway.zip . \
    -x "*.sh" \
    -x ".git/*" \
    -x ".gitignore" \
    -x ".devcontainer/*" \
    -x ".build/*" \
    -x ".vscode/*";