#!/bin/bash
# zips the whole plugin that can be uploaded to wordpress sites
# Removes local development endpoint in wc-splitmo-constants.php

FILE=wc-splitmo-constants.php

mkdir -p ./.build && mv $FILE .build
sed '/local/d' ./.build/$FILE > ./$FILE

zip -r wc-splitmo-payment-gateway.zip . \
    -x "*.sh" \
    -x ".git/*" \
    -x ".gitignore" \
    -x ".devcontainer/*" \
    -x ".build/*" \
    -x ".vscode/*";

mv -f ./.build/$FILE .