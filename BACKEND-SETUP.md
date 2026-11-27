# Backend & Admin Panel — Setup Guide

Ei website e ekta PHP + MySQL backend ache: order database e save hoy, customer er file thake, ar **admin panel** theke order ar product manage kora jay.
GitHub Pages e PHP chole na — tai domain + hosting kinar por nicher steps follow korun. Tar age porjonto site FormSubmit diye email e order pathay, kono kaj atkabe na.

## Hosting kinar shomoy ki dekhben
- **cPanel hosting** with **PHP 7.4 ba tar beshi** (8.x hole bhalo) ar **MySQL**
- **SSL (https)** free thakle bhalo — admin login nirapod thake
- Kom pokkhe 1 GB storage (customer der file er jonno)

## Setup (ekbar korlei hobe)

1. **Database banan** — cPanel → *MySQL Databases*:
   - Ekta database banan (jemon `inspired_db`)
   - Ekta user banan, strong password din
   - User ke database e add korun, **ALL PRIVILEGES** din

2. **Config file** — `api/config.sample.php` copy kore `api/config.php` nam din, tarpor database er nam, user ar password bosan.
   `config.php` GitHub e jabe na (`.gitignore` e ache), tai password nirapod.

3. **File upload** — puro project (sob file + folder) cPanel *File Manager* e `public_html` e upload korun.

4. **Setup page chalan** — browser e jan: `https://apnar-domain.com/admin/setup.php`
   - Admin username ar password (10+ okkhor) din
   - Eta table banabe, website er sob product database e import korbe

5. **`admin/setup.php` delete korun** — setup shesh hole File Manager theke file ta muche din.

6. **Login** — `https://apnar-domain.com/admin/` e giye login korun.

## Admin panel e ja ache
- **Dashboard** — ei mashe koyta order, koto takar, koyta advance verify korte hobe, koyta quote baki, best seller.
- **Orders** — sob order, status diye filter, nam/phone/Trx ID diye search.
  Prottek order e: customer er tottho, product, dam, Trx ID, customer er mark kora chhobi, upload kora file download, status bodlano, private note, ek click e WhatsApp message (confirm, quote, advance paid, ready).
- **Products** — notun product add, edit, chhobi upload, dam bosano, website theke lukano/dekhano, delete.
  Ekhane dam bodlale website e sathe sathe bodle jay.

## Dashboard er 10 ta section
| # | Section | Ki dekhay / kivabe ashe |
|---|---|---|
| 1 | This month | Ei mashe koyta order ar koto takar |
| 2 | Today new | Aj sara dine koyta order eshechhe |
| 3 | Hold | **Auto:** je order New/Confirmed obosthay din par hoye geche (shipping e jay ni) |
| 4 | Important | Customer "Need it by" date dile **auto** important, date er 2 din age reminder (email + dashboard) |
| 5 | Confirm | New theke ek click e (ba onekgulo ekshathe) confirm |
| 6 | Shipping | **Auto:** courier e entry korlei (Steadfast button ba tracking code dile) |
| 7 | Waiting | **Auto:** courier hub e receive korle. 5 din er beshi thakle lal kore dekhay — parcel miss hole bujha jay |
| 8 | Delivery | **Auto:** courier deliver korle |
| 9 | Partial | **Auto:** customer shudhu delivery charge diye partial korle |
| 10 | Return | **Auto:** customer door e cancel korle |
| 11 | Fake order | **Auto:** order ashar sathe sathe customer er number check kore parcel nayar chance (%) ber kore. 40% er kom hole ekhane ashe; beshi hole New e thake |

### Fake order er % kivabe hishab hoy
- Shuru 70% (notun customer, kono somossa nai)
- Advance diye thakle +20 · Nam ashol mone na hole (number/test/khub chhoto) −25 · Address khub chhoto −15
- Ek number theke 24 ghontay 2+ order −20 · Ek number e 2+ alada nam −15 · ৳10,000+ COD advance chara −10
- Ei number e age apnar kache parcel thakle (delivered vs return/partial) sei record beshi gurutto pay — 5+ parcel hole 80% porjonto
- Customer kokhono "fake" dekhte pay na; order page theke "Not fake — move to New" ba "Mark fake" kora jay. `config.php` e `fake_threshold` bodle 40% er sima bodlano jay.

"In production" status ta custom order banano cholar shomoy use korben — ei status e thakle order Hold e jay na.

## Courier connect (Steadfast, Pathao, RedX)
Admin panel → ⋮ menu → **Courier → Courier connections**:
1. Je courier use koren tar card e key/password bosiye **Save & connect** — sathe sathe test kore "Connected" dekhabe (bhul hole courier er message dekhabe).
   - **Steadfast:** API Key + Secret Key
   - **Pathao:** Client ID, Client Secret, merchant email, password, Store ID
   - **RedX:** API access token (pickup store ID optional)
2. **Make default for one click** — ei courier e one-click order jabe.
3. Order page e **Send to …** — courier bachai, COD nijei bosano thake. Pathao te city/zone/area, RedX e area (address theke nijei khuje ney).
4. Orders list e tick diye ekshathe default courier e pathano jay.
5. Shob courier er status protি 15 minute e nijei update hoy.

Note: Steadfast official API docs mene kora. Pathao/RedX er official public docs nai, tai community SDK theke banano — prothombar connect kore **Test** chap diye check korben; kono field mile na gele courier er message dekhabe.
Onno courier (Sundarban, SA Paribahan) hole order page e tracking code haate likhe din.

## Facebook (Meta) Pixel
Admin panel → ⋮ → **Marketing → Facebook Pixel**:
1. Meta **Events Manager → Data sources** → apnar Pixel → **Pixel ID** copy kore bosan, **Pixel on** kore Save.
2. (Recommended) Events Manager → Settings → **Conversions API → Generate access token** — token bosan. Ete ad blocker / iPhone e o order gona hoy.
3. Test korte: Events Manager → **Test events** theke test code bosan → "Send server test event". Live korar age test code muche din.
4. Business Settings → Brand safety → **Domains** e domain verify korun (DNS TXT).
Website nijei pathay: PageView, ViewContent, Search, AddToWishlist, AddToCart, InitiateCheckout, CustomizeProduct, Purchase, Lead, Contact (WhatsApp).
Purchase/Lead browser ar server duijaygay theke eki order code (event ID) diye jay, tai Meta ekbar-i gone.
GitHub Pages e (PHP chara) chalale `index.html` e `META_PIXEL_ID = ''` er bhetore Pixel ID likhe din.

## Bangla QR (payment)
Admin → ⋮ → **Payment → Bangla QR**:
- Apnar bank / bKash Merchant / Nagad Merchant account theke paoa **Bangla QR chobi** (JPG/PNG/WEBP ba screenshot) upload korun, dokaner nam likhun, **Bangla QR on** kore Save.
- Website er checkout e notun option **"Bangla QR"** ashe. Landing page e ashe jodi oi page er payment **"Cash on Delivery + pay first"** ar **Bangla QR** e tik deya thake (Edit page → Order form & contact).
- Customer QR scan kore taka pathay, tarpor **Transaction ID** likhe order kore. Admin e order er payment e "Bangla QR" ar Trx ID dekhay.
- Ei QR "static": customer ke app e taka er porimaan nije likhte hoy (page e mot taka dekhano thake).
- Order confirm korar age **apnar bank / merchant app e Trx ID mile dekhun**.
- QR bodlate notun chobi upload korun; bondho korte switch off ba "Remove QR".

## Customer Insights (⋮ menu e alada group)
Ekhane 2 ta option: **Order map** ar **Ad audience (Facebook)**.

### Ad audience (Facebook)
- Ek ba onek product bachai korun (kichu na bachaile shob product), area (shob / Dhaka bhitore / baire / kono jela), somoy, kon order, chaile shudhu 2+ bar kena customer.
- **Customer list (CSV)**: Meta Ads Manager → Audiences → Create audience → Custom audience → Customer list e upload korun (phone, fn, ln, ct, country — Meta nijei chene).
- **With customer value (CSV)**: value-based lookalike er jonno, ke koto taka kinlo shoho.
- **Copy phone numbers**: shob nombor +8801… format e copy.
- Tarpor Lookalike audience (Bangladesh, 1%) banan; Boost post e Audience e eta bachai korun.
- Meta te ad chalate list e prai 100 jon lagbe.

### Order map
Admin → ⋮ → **Customer Insights → Order map**: kon jela theke ar Dhaka er kon thana/area (Mirpur, Uttara, Jurain …) theke beshi order ashe, map e oi zone er shimana rong kore dekhay (gaḍho lal = beshi order, holud = kom, faka = kono order nai; shobcheye beshi order er zone er border mota).
- Zone er shimana `assets/geo/` folder e thake (geoBoundaries, BBS/OCHA, CC BY 3.0 IGO). Hosting e upload korar shomoy ei folder o upload korben.
- Order er thikana pore 64 jela ar Dhaka er area chene (English ba Bangla dui bhabei).
- Pashe jela ar Dhaka area er talika: order songkha ar bikri. Click korle map oikhane jay.
- Filter: product, somoy, kon order.
- Jei thikana e jelar nam nai, shegulo "Not on the map" e dekhay. Order khule thikana e jelar nam likhe dile map e chole ashe.
- Map er jonno internet lage (OpenStreetMap).

## Customers by product
Admin → ⋮ → **Product → Customers by product** (ba All products e product er pashe 👥 button):
- Protita product er alada customer list: nam, nombor, thikana, koto bar kinlo, koto pis, koto taka, shesh order, status, kotha theke order (landing page / cart / custom).
- Filter: somoy (30 din / 3 mash / 12 mash / shob), kon order (asol / shudhu delivered / shob), search, sort.
- **Download Excel**: ek product ba shob product er list (.csv, Excel e Bangla thik dekhay). Phone 880… format e, Facebook custom audience e upload kora jay.
- Protita customer er pashe WhatsApp, Call ar tar shob order dekhar button.

## Landing Pages (protita product er sales page)
Admin panel → ⋮ → **Landing Pages**:
- **All landing pages**: protita product er page — on/off, views, order, kotojon order korlo (%). "Copy link" diye Facebook ad e link din (`landing.php?p=<product id>`).
- Notun product add korlei tar landing page ready Bangla lekha diye nijei toiri hoy (off korte chaile "Auto landing page" switch).
- **Timed offer**: Edit page e "Offer price" likhe "5 hours / 24 hours / 3 days / 7 days" chap din. Offer cholakalin page ar order dui jaygay offer dam; time shesh hole offer nijei bondho hoy, normal dam fire ashe (timer shob shomoy shotti). Notun offer chalate abar button chapun.
- **Edit page**: 17 ta design theke ekta bachai, headline, offer, regular price (kata dam), countdown, photo, video (YouTube/Facebook), point, box e ki ache, free gift, customer photo, FAQ, payment (COD / COD + bKash). Dan pashe live preview.
- **Power Sell** design (amar nijer banano): product er chobi theke nijei rong nay; 1/2/3 pis card; asol "গত ৭ দিনে X জন অর্ডার" (3 ba tar beshi hole); chobir pashe bhese thaka badge; "যাওয়ার আগে" box e 3 ghor puron kore COD order; upore scroll bar; magnetic button.
- Shob design e: "আর ৳X যোগ করলে ডেলিভারি ফ্রি" bar, ar ager customer er nam/nombor/thikana ei phone e mone rakhe ("মুছে ফেলুন" diye muche dite pare).
- **Templates**: 17 ta design er preview; "Make default" ba "Use on all".
- Shop e product e click korle landing page khule. Landing page off thakle ager product page khule.
- Order form: quantity, "sathe nite paren" add-on, Dhaka bhitore/baire charge, COD/bKash. Order ashe admin e (fake check, courier, Pixel shob kaj kore).
- Dam nai emon product e order form er bodole Call/WhatsApp button thake.
- Landing page er photo gulo `assets/landing/` e thake.

## Auto update (Cron Job) — joruri
Status nijei bodlano, Hold ar reminder email er jonno cPanel e ekta cron job din:
- cPanel → **Cron Jobs** → *Every 15 minutes*
- Command: `php /home/APNAR_USER/public_html/api/cron.php`
- (Host URL chaile: `https://apnar-domain.com/api/cron.php?key=APNAR_CRON_KEY` — `config.php` e `cron_key` e lomba random lekha din)

Cron na dileo admin panel khulle protি 15 minute e eta nijei chole; tobe reminder email time moto pete cron lagbe.

## Nijer computer e test korte chaile
`api/config.php` e `'driver' => 'sqlite'` din, tahole MySQL chara-i chalano jay (PHP lagbe).

## Customer review (asol review)
- Shudhu **deliver hoyeche** emon order er customer review dite pare — protita order er alada gopon link (`review.php?o=…&t=…`), keu anuman kore banate pare na.
- Order **Delivered** hole: customer email dile review link nijei email e jay. Na dile admin er order page e **"Ask for review on WhatsApp"** button chapun (Bangla message + link ready) ba **"Copy review link"**.
- Customer star (১–৫), lekha ar chobi dey, ar "website e dekhano jabe" tik dey. Chobi theke phone er location data muche fela hoy.
- Admin → ⋮ → **Product → Reviews**: "Show on website" chaple website, landing page ar product er star e dekhay (**✓ Verified Buyer**). 5 star na holeo shotti review approve korun — tate bishshash bare.
- Product er star ekhon shudhu asol review theke hishab hoy.
- Hosting e `api/config.php` e `'site_url' => 'https://inspiredcreationbd.com'` din (cron/email e link thik thakar jonno).

## Customer email (order confirmation)
- Website checkout, custom order form ar landing page e **Email (optional)** box ache. Email dile customer Bangla te order confirmation pay: order nombor, product, dam, delivery charge, payment, thikana, WhatsApp button.
- Customer email er "Reply" korle apnar order email e (`order_email`) ashe.
- Admin e order er "Customer" ongshe email ar "Order confirmation emailed" lekha dekhay; pathano na gele lal lekha ashe. Order search e email diyeo khoja jay.
- Email Facebook Pixel / Ad audience e o jay (hash kore) — match aro bhalo hoy.
- Hosting e: cPanel → **Email Accounts** e `no-reply@inspiredcreationbd.com` banan, ar **Email Deliverability** e SPF/DKIM "Valid" korun — na hole email spam e jete pare.

## Nirapotta
Admin → ⋮ → **Settings**:
- **Security check** — shob nirapotta ek jaygay, sobuj = thik. Dashboard e kichu baki thakle holud box e dekhay.
  - **Check SSL & turn on HTTPS**: cPanel → SSL/TLS Status e AutoSSL chalanor por ei button chapun; age check kore SSL kaj kore kina, tarpor shob visitor ke https:// e pathay.
  - **Delete setup.php**: live site e setup shesh hole ek click e muche dey.
- **Backup** — "Download backup now" chaple ek .zip e shob order, customer, product, landing page, setting ar upload kora chobi/file ashe. **Proti shoptahe** download korun (7 din por dashboard mone koriye dey). Restore: cPanel → phpMyAdmin → Import → `database.sql`.
- **Account & admins** — nijer password bodlano, helper der jonno alada login banano/muche fela.
- **Password bhule gele**: cPanel → File Manager → `data` folder e `reset-admin.txt` file banan, bhitore `username:NotunPassword` likhun, tarpor `apnar-site.com/admin/recover.php` khulun. Password bodle jabe ar file nijei muche jabe.

Aro:
- Bhul password: ek username e 5 bar ba ek jayga theke 10 bar bhul dile 15 minute login bondho (database e gona hoy, browser cookie muche eta ekano jay na).
- Order spam limit: ek internet connection theke 10 minute e 5 ta / dine 20 ta, ar ek nombor theke ghontay 4 ta er beshi order neya hoy na.
- Visitor ra kono PHP error dekhe na; error gulo `data/php-errors.log` e jay. `api/config.php` e `'debug' => false` rakhun.
- `uploads/` folder er file sorasori web theke khola jay na — shudhu login kora admin download korte pare.
- `api/config.php` ar `data/` folder kokhono GitHub e ba hosting e upload korben na (hosting nijer database banabe — nijer computer er test order tai live e jabe na).
