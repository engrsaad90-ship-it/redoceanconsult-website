# Putting www.redoceanconsult.com online (Namecheap)

You already have Namecheap hosting, so this takes about 10 minutes. No coding needed.

## 1. Log in to cPanel
1. Sign in at namecheap.com → **Dashboard** → **Hosting List**.
2. Next to your hosting plan click **Go to cPanel**.

## 2. Upload the website
1. In cPanel open **File Manager**.
2. Open the **public_html** folder (this is your website's root).
   - If it contains old files such as a placeholder `index.html`, delete or move them.
3. Click **Upload** and choose `redoceanconsult-website.zip`.
4. Back in File Manager, right-click the zip → **Extract** → extract into `/public_html`.
5. Make sure `index.html`, `contact.php`, `.htaccess` and the `assets` folder sit **directly inside public_html**
   (not inside a sub-folder). Turn on **Settings → Show Hidden Files** to see `.htaccess`.
6. Delete the zip file afterwards.

## 3. Turn on HTTPS (free SSL)
1. In cPanel search for **SSL/TLS Status** (or **Namecheap SSL**).
2. Select redoceanconsult.com and www.redoceanconsult.com → **Run AutoSSL**.
3. The included `.htaccess` sends every visitor to `https://www.redoceanconsult.com` automatically.
   If the site shows an error before SSL is active, temporarily rename `.htaccess` and rename it back once SSL works.

## 4. Check the domain points to the hosting
If the domain was bought at Namecheap with the same hosting, it already does.
Otherwise: Domain List → Manage → Nameservers → **Namecheap Web Hosting DNS**.

## 5. Test the contact form
1. Open https://www.redoceanconsult.com, scroll to **Request a consultation**, and send a test.
2. It arrives at **saadsr@redoceanconsult.com** (check spam the first time).
3. To change the recipient, edit line 5 of `contact.php` in File Manager.
   If messages don't arrive, create the mailbox `website@redoceanconsult.com` in cPanel → Email Accounts
   (it is used as the sender address).

## Editing text later
All text is in `index.html`. In File Manager, right-click it → **Edit**, change the words, and save.
Images are in `assets/img` (replace a file with one of the same name to swap a photo).

---

# Online ordering (Baseline, EOT claim, Quantum claim)

Clients click **Order online**, tick the deliverables they need, enter project details, see an instant
itemised quotation (with 15% VAT), approve it, and are sent to payment.

## Set your prices — `pricing.json`
Open `pricing.json` in cPanel File Manager → Edit. For each service you can change:
- `price` – SAR before VAT for the smallest project band
- `days` – working days that item adds to the delivery estimate
- `required: true` – always included (cannot be unticked)
- `default: true` – ticked by default, client can untick
- `sizeBands` – multipliers by project contract value (e.g. 1.4 = +40%)
- `express` – surcharge and speed-up for urgent orders
- To add a new option, copy an item block and give it a new unique `id`.
The website and `order.php` both read this one file, so the price a client sees is always the price charged.
The prices supplied are **examples** — replace them with your own before going live.

## Set up payment — top of `order.php`
- **Payment link with amount:** set `$PAYMENT_URL`, e.g. `https://www.paypal.me/YOURNAME/{amount}SAR`.
  The client is redirected there automatically after approving.
- **Bank transfer (default):** leave `$PAYMENT_URL = ''` and fill in your bank name and IBAN in `$BANK`.
  The client sees the details on screen and by email, with the order reference.
- **Card / mada / Apple Pay (Moyasar, Tap, PayTabs, HyperPay):** these need an API key so each order gets
  its own invoice. Open an account and send the API details to your developer to connect.

## Where orders go
- Every order is emailed to saadsr@redoceanconsult.com, and the client receives a copy of the quotation.
- Every order is also saved to `public_html/orders/orders.csv` (download it from File Manager and open in Excel).
  The folder is locked so the public cannot view it.
