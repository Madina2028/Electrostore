# Electrostore

E-commerce site for electronic devices

## Local database setup

Copy `config/db.local.example.php` to `config/db.local.php` and set your local
database credentials in the copied file. The local credentials file is excluded
from Git; do not commit real passwords or production credentials.

## Shop subdomain deployment

The storefront is `shop.php`. To serve it at `https://shop.electrostore.me/` on
an Azure Windows VM running XAMPP:

1. In the DNS provider for `electrostore.me`, add an **A** record named `shop`
   pointing to the VM's stable public IPv4 address. Allow inbound TCP ports 80
   and 443 in both the Azure network security group and Windows Firewall.
2. Ensure the project and its production `config/db.local.php` are deployed
   under `C:\xampp\htdocs\Electrostore`. Keep the database file private and
   configure credentials for the VM's database.
3. Enable Apache virtual hosts in `C:\xampp\apache\conf\httpd.conf` by
   uncommenting the `httpd-vhosts.conf` include. Add this to
   `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

   ```apache
   <VirtualHost *:80>
       ServerName shop.electrostore.me
       DocumentRoot "C:/xampp/htdocs/Electrostore"
       <Directory "C:/xampp/htdocs/Electrostore">
           Options FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
       DirectoryIndex shop.php
       ErrorLog "logs/shop-error.log"
       CustomLog "logs/shop-access.log" common
   </VirtualHost>
   ```

4. Validate the Apache configuration, then restart Apache from the XAMPP
   Control Panel. Configure an SSL certificate for `shop.electrostore.me` in
   Apache before directing customers to the HTTPS URL.

The virtual host uses the project folder as its document root, so product
images, stylesheets, login, cart, and product-detail links continue to work
from the subdomain. If the main site is also hosted in that folder, give it a
separate virtual host with its own `ServerName` and `DirectoryIndex`.

The shop page supports product search, category filtering, and sorting by name,
price, or newest. Categories are inferred from product names and descriptions,
so no database migration is needed. The header cart badge shows the current
session's total item quantity.
