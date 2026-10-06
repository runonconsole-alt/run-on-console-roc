RUN ON CONSOLE - PHASE 4 (naye products, social profiles, initials avatars)
===========================================================================

Is update mein kya hai
- Purane 29 dummy products hata diye. Sheet ke 122 products 6 categories mein:
  Keyboards 21, Headsets & Audio 23, Mice 20, Speakers & Soundbars 25, Monitors 20, GPUs 13.
  (Katana V3 aur Arena 3 sheet mein do dafa thay, is liye 124 ki jagah 122.)
- Har product ka apna page: /products/<naam>/  aur Amazon ka button.
- Har category ka page: /products/category/keyboards/ (mice, audio, speakers, monitors, gpu)
- Purane product URLs 301 redirect se nayi category/product par jate hain.
- Footer mein Facebook, Instagram, Pinterest, X. CMS -> "Social & Amazon tag" se badal sakte hain.
- CMS -> "Products": sab 122 products, category aur Amazon link ke saath.
- CMS -> "Social & Amazon tag": Amazon Associates tag ek dafa daalen, har Amazon link par khud lag jayega.
- Profile avatars: SVG tasveeron ki jagah naam ke pehle 2 letters (rang user chun sakta hai).
- Fake price, fake rating aur "benchmark" wala dummy section hata diya.

INSTALL (cPanel -> Terminal)
----------------------------
1) Zip file HOME folder mein upload karein (public_html mein NAHI):
   File Manager -> upar "Home" (/home2/runoncon) -> Upload -> ROC-Phase4-Products.zip
   Agar ghalti se public_html mein chali gayi:
     mv ~/public_html/ROC-Phase4-Products.zip ~/

2) Terminal mein:
     cd ~
     unzip -o ROC-Phase4-Products.zip
     php ~/roc-phase4/install.php

   Yeh sirf DRY RUN hai, kuch change nahi hota. Output mein yeh dikhna chahiye:
     5) Database products:  29 old -> 122 new

3) Theek lage to install:
     php ~/roc-phase4/install.php --apply

   Aakhir mein "Done." aur ek rollback command print hogi. Us line ko copy karke rakh lein.

CHECK
-----
- https://runonconsole.com/products/
- https://runonconsole.com/products/category/keyboards/
- https://runonconsole.com/products/wooting-80he/   (Amazon button dabayen)
- https://runonconsole.com/products/logitech-g-pro-x-tkl-lightspeed/  -> keyboards category par jana chahiye
- Kisi bhi page ka footer: Facebook, Instagram, Pinterest, X
- CMS -> Products, CMS -> Social & Amazon tag
Purana page dikhe to Ctrl+Shift+R (hard refresh) karein.

Terminal se check:
  curl -sI https://runonconsole.com/products/logitech-g-pro-x-tkl-lightspeed/ | grep -i -E "^HTTP|^location"
  curl -s https://runonconsole.com/sitemaps/products-sitemap.xml | grep -c "<loc>"      (128 aana chahiye)

ROLLBACK (sab wapas purana)
---------------------------
  php ~/roc-phase4/install.php --rollback=/home2/runoncon/roc-phase4-backup-XXXXXXXX-XXXXXX
  (exact folder ka naam --apply ke output mein tha; ya:  ls -d ~/roc-phase4-backup-* )

Baad mein
- Google Search Console -> Sitemaps -> sitemap.xml dobara submit karein.
- Sab theek chalne ke 1 hafte baad ~/roc-phase4 aur backup folder delete kar sakte hain.
- source/ folder: React code ki badli hui files (GitHub repo ke liye).
