# Persistent Login (Roundcube)
Provides a "Keep me logged in" aka "Remember Me" functionality for [Roundcube][roundcube].

![Login with Larry Skin](docs/login_elastic.png)

## Download
You can download the plugin from the latest [release tags][github-release] on GitHub.

## Installation
- Extract the downloaded archive into Roundcube’s plugin directory
  `<roundcube>/plugins/` and rename it to `persistent_login`.

- Open the Roundcube's main configuration file `<roundcube>/config/main.inc.php`
  and add the plugin’s name to the active plugins array, e.g.:

```php
$config['plugins'] = array('persistent_login');
```

Thats it. All configuration is optional.

## Configuration (Optional)
Persistent Login provides two different ways of usage.
User-Data cookie or AuthToken cookie based.

### UserData cookie (No database extension required)
This method doesn’t require any configuration, it is enabled by default.
It stores the user’s login information encrypted in a cookie.

### AuthToken cookie (more secure, recommended)
This method have to be enabled in the plugins configuration file.
It requires the creation of a table in your __roundcubemail’s database__ as well.
The table can be created by the SQL script in the `sql/` directory of the plugin.
Execute ALL statements from the SQL file.

Enable it in config: `persistent_login/config.inc.php`
```php
$rcmail_config['ifpl_use_auth_tokens'] = true;
```

### OAuth with login redirect
Persistent Login works with OAuth as-is. However, if Roundcube's automatic login redirect setting `oauth_login_redirect` is true, the user will not have the opportunity to select "Keep me logged in" because Roundcube sends the user directly to the OAuth server for authentication (the login screen is not shown at all).

To enable Persistent Login in this case, change Roundcube's `oauth_login_redirect` setting to __false__. In config/config.inc.php:
```php
$config["oauth_login_redirect"] = false;
```

Then, enable Persistent Login's `ifpl_oauth_login_redirect` setting. In `plugins/persistent_login/config.inc.php`
```php
$rcmail_config['ifpl_oauth_login_redirect'] = true;
```

The login screen will be displayed showing only the "Keep me logged in" checkbox and a button to authenticate with the OAuth server.

[roundcube]: http://roundcube.net/
[github-release]: https://github.com/mfreiholz/persistent_login/releases