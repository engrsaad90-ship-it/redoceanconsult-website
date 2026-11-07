# Red Ocean Consult — website

Source for **www.redoceanconsult.com**: construction planning, delay analysis, EOT and quantum claims across Saudi Arabia.

## What's here
| File | Purpose |
|---|---|
| `index.html` | The whole website (single page) |
| `pricing.json` | Online-order prices and options — edit prices here |
| `order.php` | Online order handler: recalculates the quote, emails it, returns payment details |
| `contact.php` | Contact form handler (emails saadsr@redoceanconsult.com) |
| `404.html`, `.htaccess`, `robots.txt`, `sitemap.xml` | Error page, HTTPS redirect, SEO |
| `assets/` | Logo, photos and client logos |
| `DEPLOY-GUIDE.md` | How to publish on Namecheap and set up payments |

## Hosting
Runs on Namecheap shared hosting (PHP required for the contact and order forms).
Upload everything except `README.md`, `DEPLOY-GUIDE.md` and `.gitignore` into `public_html`.
