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

## Set up payment
Payment settings are in a private file, `redocean-config.php`, kept one level above `public_html`
(see "PayPal payments" below). Clients can pay by PayPal or card, and/or bank transfer if you add an IBAN.

## Where orders go
- Every order is emailed to saadsr@redoceanconsult.com, and the client receives a copy of the quotation.
- Every order and every payment is also saved to `public_html/orders/orders.csv` (download it from File Manager and open in Excel).
  The folder is locked so the public cannot view it.

---

# Updating the live site from GitHub (cPanel Git Version Control)

One-time setup:
1. cPanel → **Git™ Version Control** → **Create**.
2. **Clone a Repository**: on.
   - Clone URL: `https://github.com/engrsaad90-ship-it/redoceanconsult-website.git`
   - Repository Path: `repositories/redoceanconsult-website` (NOT public_html)
   - Repository Name: `redoceanconsult-website`
3. Click **Create**.
4. Click **Manage** next to the repository → **Pull or Deploy** tab → **Deploy HEAD Commit**.
   The `.cpanel.yml` file copies the website into `public_html`.

Every later update (after changes are pushed to GitHub):
**Manage → Pull or Deploy → Update from Remote**, then **Deploy HEAD Commit**.

---

# Automatic deployment (every push to GitHub goes live)

`.github/workflows/deploy.yml` tells cPanel to pull and deploy whenever `main` changes.
Requires the cPanel Git repository from the section above to exist at
`repositories/redoceanconsult-website`.

One-time setup:
1. **Create a cPanel API token:** cPanel → search **Manage API Tokens** → **Create**.
   Name it `github-deploy`, leave "expires" off, click **Create**, and copy the token (it is shown only once).
2. **Find your server name:** cPanel home page → right-hand **General Information** panel →
   **Server Name** / hostname (looks like `server123.web-hosting.com`).
3. **Add three secrets on GitHub:** repository → **Settings → Secrets and variables → Actions →
   New repository secret**:
   - `CPANEL_HOST` = the server hostname, e.g. `server123.web-hosting.com`
   - `CPANEL_USER` = your cPanel username, `redohmgq`
   - `CPANEL_TOKEN` = the API token from step 1
4. Test: GitHub → **Actions** → **Deploy to Namecheap** → **Run workflow**. A green tick = live.

From then on every push deploys automatically. Never paste the token anywhere except GitHub Secrets.

---

# PayPal payments

Clients approve the quotation, then pay with PayPal or any debit/credit card in a PayPal pop-up.
PayPal does not support SAR, so it charges the US-dollar equivalent at the fixed rate of 3.75 SAR = 1 USD
(e.g. SAR 12,650 → USD 3,373.33). The website shows both amounts.
Your server creates every PayPal payment from the saved quotation and verifies the amount with PayPal
before marking an order paid, so the price cannot be changed in the browser.

## 1. Get your PayPal keys
1. You need a **PayPal Business** account (paypal.com → Sign up → Business, or upgrade your personal account).
2. Go to **developer.paypal.com** and log in with that account.
3. **Apps & Credentials** → make sure **Sandbox** is selected → **Create App** → name it `Red Ocean Website`.
4. Copy the **Client ID** and **Secret**.

## 2. Create the private settings file on Namecheap
1. cPanel → **File Manager**. Go to your home folder `/home/redohmgq/` — the folder that CONTAINS
   `public_html`, not inside it.
2. **+ File** → name it `redocean-config.php` → right-click → **Edit**.
3. Paste the contents of `config.sample.php` from GitHub, fill in `paypal_client_id` and `paypal_secret`,
   and (optionally) your bank details. Save.
   Never put this file inside `public_html` or on GitHub.

## 3. Test with sandbox (no real money)
1. developer.paypal.com → **Testing Tools → Sandbox Accounts** shows a test *Personal* account
   (email + password).
2. On your website, place an order, click **PayPal**, and log in with that test account. Approve the payment.
3. You should see **Payment received**, get a "PAID" email, and see a `paid (PayPal)` row in `orders/orders.csv`.

## 4. Go live
1. developer.paypal.com → switch to **Live** → **Create App** → copy the live Client ID and Secret.
2. In `redocean-config.php` set `'paypal_mode' => 'live'` and paste the live keys. Save.
3. Make one small real payment to confirm, then refund it from your PayPal account.

PayPal charges its standard fees per transaction; payouts land in your PayPal balance, from which you can
withdraw to your Saudi bank account.
