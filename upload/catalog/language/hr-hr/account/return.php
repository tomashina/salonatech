<?php
// Croatian   v.2.x.x     Datum: 01.10.2014		Author: Gigo (Igor Ilić - igor@iligsoft.hr)
// Heading
$_['heading_title']      = 'Povrat artikala';

// Text
$_['text_account']       = 'Korisnički račun';
$_['text_return']        = 'Informacije o povratu artikala';
$_['text_return_detail'] = 'Detalji povrata';
$_['text_description']   = '<p>Molim popunite doljnji formular da biste zatražili (dobili) broj zahtjeva za povrat robe (RMA).</p>';
$_['text_order']         = 'Informacije o narudžbi';
$_['text_product']       = 'Informacije o artiklu';
$_['text_reason']        = 'Razlog povrata';
$_['text_message']       = '<p>Zahvaljujemo se što ste nam poslali Vaš zahtjev za povratom artikala. Vaš zahtjev bit će proslijeđen u odgovarajući odjel relevantnom djelatniku za obradu.</p><p> Bit ćete obaviješteni putem e-maila o statusu Vašeg zahtjeva.</p>';
$_['text_return_id']     = 'Zahtjev broj:';
$_['text_order_id']      = 'Narudžba broj:';
$_['text_date_ordered']  = 'Datum narudžbe:';
$_['text_status']        = 'Status:';
$_['text_date_added']    = 'Datum dodavanja:';
$_['text_comment']       = 'Komentari uz zahtjev za povrat';
$_['text_history']       = 'Povijest povrata';
$_['text_empty']         = 'Do sad niste napravili niti jedan povrat!';
$_['text_agree']         = 'Pročitao sam i slažem se s <a href="%s" class="agree"><b>%s</b></a>';

// Column
$_['column_return_id']   = 'Povrata artikala broj';
$_['column_order_id']    = 'Narudžba broj';
$_['column_status']      = 'Status';
$_['column_date_added']  = 'Datum dodavanja';
$_['column_customer']    = 'Kupac';
$_['column_product']     = 'Naziv artikla';
$_['column_model']       = 'Model';
$_['column_quantity']    = 'Količina';
$_['column_price']       = 'Cijena';
$_['column_opened']      = 'Otvoren';
$_['column_comment']     = 'Komentar';
$_['column_reason']      = 'Razlog';
$_['column_action']      = 'Akcija';


// Entry
$_['entry_order_id']     = 'Narudžba broj';
$_['entry_date_ordered'] = 'Datum narudžbe';
$_['entry_firstname']    = 'Ime';
$_['entry_lastname']     = 'Prezime';
$_['entry_email']        = 'E-mail';
$_['entry_telephone']    = 'Telefon';
$_['entry_product']      = 'Naziv artkla';
$_['entry_model']        = 'Model';
$_['entry_quantity']     = 'Količina';
$_['entry_reason']       = 'Razlog povrata';
$_['entry_opened']       = 'Artikl je otvoren';
$_['entry_fault_detail'] = 'Neispravnost ili druge pojedinosti';
// $_['entry_captcha']      = 'Upišite kod u polje (kućicu) ispod';

// Error
$_['text_error']         = 'Zahtjev za povrat koji ste zatražili nije pronađen!';
$_['error_order_id']     = 'Broj narudžbe je obavezan podatak!';
$_['error_firstname']    = 'Ime mora sadržavati između 1 i 32 znaka!';
$_['error_lastname']     = 'Prezime mora sadržavati između 1 i 32 znaka!';
$_['error_email']        = 'Čini se da je navedena e-mail adresa neispravna!';
$_['error_telephone']    = 'Telefon mora sadržavati između 3 i 32 znaka!';
$_['error_product']      = 'Naziv artikla mora imati više od 3 i manje od 255 znakova!';
$_['error_model']        = 'Model artikla mora imati više od 3 i manje od 64 znaka!';
$_['error_reason']       = 'Morate odabrati razlog povrata artikla!';
// $_['error_captcha']      = 'Kod za provjeru (verifikaciju) ne odgovara onom sa slike!'; // postojalo u verziji OC 2.0.3.1
$_['error_agree']        = 'Upozorenje: Morate prihvatiti (složiti se s) %s!';