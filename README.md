
PayPal Donate Shortcode plugin
==============================

This Wordpress plugin providing a shortcode for accepting donations through
PayPal. The button can optionally show the number of donations made in a
bubble.

Example shortcode:

```
[paypal-donate id="my-widget"]
```

The shortcode will insert a small button that links to the PayPal donation
page. The button is loaded through an iframe to avoid clashes with existing
style sheets. This iframe can also be embedded in external web sites that do not
use Wordpress. Further info can be shown to the right of the button in a
bubble.

Go to the PayPal Shortcode settings page to set up widgets. Follow the
instructions to find you PayPal business ID and choose an identifier for the
widget that will be used in the shortcode. Name and currency can be chosen as
desired (although PayPal will limit the possible currencies) and the name will
appear on the donation page.

Widgets
-------
It is also possible to use shortcodes in Wordpress widgets (e.g. in the side
bar) but this requires an additional plugin to be installed (e.g
http://wordpress.org/extend/plugins/shortcodes-in-sidebar-widgets/).

Demo
----
Here is a demo of the plugin: http://jonls.dk/ . The button appears in the
sidebar.

Install
-------
Copy the directory `paypal-donate` into `/wp-content/plugins`.
