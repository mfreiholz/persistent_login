# Persistent Login (Roundcube)

[![Packagist Downloads](https://img.shields.io/packagist/dt/texxasrulez/persistent_login?style=plastic&logo=packagist&logoColor=white&label=Downloads&labelColor=blue&color=gold)](https://packagist.org/packages/texxasrulez/persistent_login)
[![Packagist Version](https://img.shields.io/packagist/v/texxasrulez/persistent_login?style=plastic&logo=packagist&logoColor=white&label=Version&labelColor=blue&color=limegreen)](https://packagist.org/packages/texxasrulez/persistent_login)
[![Github License](https://img.shields.io/github/license/texxasrulez/persistent_login?style=plastic&logo=github&label=License&labelColor=blue&color=coral)](https://github.com/texxasrulez/persistent_login/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/texxasrulez/persistent_login?style=plastic&logo=github&label=Stars&labelColor=blue&color=deepskyblue)](https://github.com/texxasrulez/persistent_login/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/texxasrulez/persistent_login?style=plastic&logo=github&label=Issues&labelColor=blue&color=aqua)](https://github.com/texxasrulez/persistent_login/issues)
[![GitHub Contributors](https://img.shields.io/github/contributors/texxasrulez/persistent_login?style=plastic&logo=github&logoColor=white&label=Contributors&labelColor=blue&color=orchid)](https://github.com/texxasrulez/persistent_login/graphs/contributors)
[![GitHub Forks](https://img.shields.io/github/forks/texxasrulez/persistent_login?style=plastic&logo=github&logoColor=white&label=Forks&labelColor=blue&color=darkorange)](https://github.com/texxasrulez/persistent_login/forks)
[![Donate Paypal](https://img.shields.io/badge/Paypal-Money_Please!-blue.svg?style=plastic&labelColor=blue&color=forestgreen&logo=paypal)](https://www.paypal.me/texxasrulez)

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

[roundcube]: http://roundcube.net/
[github-release]: https://github.com/mfreiholz/persistent_login/releases

## Versioning
- `persistent_login` now keeps its canonical version in `persistent_login::PLUGIN_VERSION` inside `persistent_login.php`.
- `persistent_login::info()` exposes the plugin metadata array used for self-identification.
- Development builds should use a `+dev` suffix such as `1.0.2+dev`.
- Release builds should use a clean tagged version such as `1.0.2`.

For a release bump:
1. Update `persistent_login::PLUGIN_VERSION` in `persistent_login.php` or run `sh scripts/bump-version.sh 1.0.2`.
2. Update `CHANGELOG.md`.
3. Create the matching release tag after verification.

