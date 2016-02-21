# Persistent Login (Roundcube)
This server-side plugin is useful for all Roundcube users who don’t
like to log into their mail account each time they open their browser.
The plugin stores a persistent login cookie which automatically logs
the user in the next time he or she visits the [Roundcube][roundcube] web mailer.

![Login with Larry Skin](docs/login_larry.png)

## Download
You can download the plugin from the latest [release tags][github-release] on GitHub.

## Installation
- Extract the downloaded archive into Roundcube’s plugin directory
  `<roundcube>/plugins/` and rename it to `persistent_login`.

- Open the Roundcube's main configuration file `<roundcube>/config/main.inc.php`
  and add the plugin’s name to the active plugins array, e.g.:

```php
// List of active plugins (in plugins/ directory)
$config['plugins'] = array('persistent_login');
```

## Configuration
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
// Turn on feature  to use tokens.
$rcmail_config['ifpl_use_auth_tokens'] = true;
```

[roundcube]: http://roundcube.net/
[github-release]: https://github.com/mfreiholz/Roundcube-Persistent-Login-Plugin/releases