# SplitMo Woocommerce Devcontainers Guide

Welcome to the Splitmo Payment Plugin! This plugin is designed to seamlessly integrate with WooCommerce, providing a reliable and secure payment gateway for your online store. With this plugin, you can enhance your customers' shopping experience by offering a variety of payment options while ensuring the safety of their transactions.

This directory contains all of the needed tools and applications in order to test and debug our splitmo plugin. This is possible with the use of vscode devcontainers to easily manage wordpress plugin development

### Tools and Applications
This devcontainer comes with pre-installed packages and pre-configured test items for the plugin development this includes:
- **wordpress** with pre-installed plugins and test product
    - woocommerce
    - all-in-one-migration
- **php composer**
- **zip** for building the plugin
- **xdebug** for debugging
- **maria db** docker instance
- **phpmyadmin** docker instance

## Setup
In order to run in a devcontainer environment you need the following:

- vscode with [ms-vscode-remote.remote-containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) installed
- [docker](https://docs.docker.com/engine/install/)
- [portainer](https://hub.docker.com/r/portainer/portainer-ce) installed via docker (optional for easier container management)

### Running the devcontainer
**⚠️ Before running the container you must setup your environment variables in order to run successfully run the container with no errors. Check [environment variables guide](#environment-variables) ⚠️** 

If you failed to do so you must **rebuild** the container and **delete** all mounted volumes related to the previous container on your docker container.

To run the container, check out a quick [tutorial](https://code.visualstudio.com/docs/devcontainers/tutorial) on how to create a container. In this case we'll not create a **"New Container"** but instead **"Open Folder in Container"** with the existing .devcontainer configurations.

### Setup your wordpress for development
On your the first wordpress creation some configurations are already set but you must check the following
- Check if the splitmo plugin is in test mode
- Check if the test endpoint is pointed to your local splitmo server environment. There must be ``local`` added to your ``Test Endpoint`` options on your splitmo plugin. If not, it is by default connected to our remote sandbox environment. To add ``local`` as your endpoint, make sure ``DEV_URL`` is added to your devcontainer ``.env`.

## Debugging
By default, the docker environment for the wordpress is pointed by default to the apache instance serving wordpress. In order to check logs of your woocommerce plugins you can try and tail logs of your woocommerce plugin by running [wc-debug.sh](../wc-debug.sh)

### Environment Variables
In order to add environment variables to our docker devcontainer create a ``.env`` file on the .devcontainer root directory and copy the [``.env.example``](.env.example). Below will explain the properties of the environment variables:

| Key                            | Value                                                                      
| ------------------------------ | ---------------------------------------------------------------------------
| ``WORDPRESS_DB_HOST``          | the hostname where the wordpress db will be located. It will also be the hostname of the db that will be created by the docker container. Set this by default to ``db``
| ``WORDPRESS_DB_USER``          | the username to access the db. It also will be the username created by the docker container.
| ``WORDPRESS_DB_PASSWORD``      | the password to access the db. It also will be the password created by the docker container.
| ``WORDPRESS_DB_NAME``          | the name to access the db. It also will be the name created by the docker container.
| ``WORDPRESS_DEBUG``            | enabled/disables debug mode for wordpress. Set this to either ``0`` or ``1``
| ``WORDPRESS_ADMIN_USERNAME``   | the username to be created to access wordpress admin.
| ``WORDPRESS_ADMIN_PASSWORD``   | the password to be created to access wordpress admin.
| ``WORDPRESS_ADMIN_EMAIL``      | the email to be created to access wordpress admin.
| ``SITE_URL``                   | The hostname and port where the wordpress can be access outside the machine **⚠️ Crucial to set this properly in order to access wordpress ⚠️**
| ``DEV_URL``                    | The hostname/port where your splitmo checkout server will be located (e.g.) ``http://192.168.1.1:8000/api/v1/``. Make sure that the endpoint is where the splitmo api starting endpoint is located. This will be added to the list of Test Endpoints in our splitmo plugin settings. 
| ``WP_RESET``                   | Resets the whole wordpress site by setting this to ``true`` or ``false``. This clears out every configuration to default when you change this before rebuilding the devcontainer. On your first run this must set to ``false``