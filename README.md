![X SMTP banner](images/banner-1544x500.png "X SMTP")

# X SMTP

**Improve ClassicPress email deliverability.**

Configure a SMTP server to send email from your site.
Adds a page under "General Settings" where you can configure the parameters of the SMTP server that will be used to send the e-mail.

*Notice that servers using OAuth (like gmail.com) are not supported.*

This plugin is multisite compatible. Each site should be configured.

### Filters

`xsmtp-phpmailer-priority` filters the priority of the hook to `phpmailer_init` (default: 10)
