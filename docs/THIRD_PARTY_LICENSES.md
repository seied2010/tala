# Third-party components

Gold original application/theme code: GPL-2.0-or-later. A copy of the GPL text ships in each install package.

Bundled in Core: TCPDF 6.10.1 from https://github.com/tecnickcom/TCPDF, GNU LGPL v3. Upstream `LICENSE.TXT`, `VERSION`, unminified runtime source and required DejaVu/Helvetica font data are preserved under `vendor/tcpdf`. DejaVu fonts have their original permissive font licenses in the corresponding font directories. TCPDF documentation/examples/tools and unrelated fonts are omitted from the runtime distribution. PDF text includes Persian shaping/RTL support and licensed local font data. The storefront's owner-supplied WOFF2 is a separate asset and is not included without its license.

Test environment only, not bundled as application dependencies: WordPress 7.1.4 (GPL-2.0-or-later), WooCommerce 11.2.2 (GPL-3.0-or-later), Yoast SEO 28.8 (GPL-3.0), MariaDB 11 container, official `wordpress:cli-php8.3` / `wordpress:cli-php8.5` images. Chromium/Playwright and axe-core are test tooling; reinstall from the pinned npm lockfile. Docker image digests and actual runtime versions appear in build evidence.

The decorative jewelry SVG is original project artwork; sample inventory and brands are fictional. No proprietary Agah source, owner photographs, credentials or restricted commercial font is bundled.
