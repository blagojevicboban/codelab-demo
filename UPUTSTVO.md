# Demo sajt codelab.tsp.edu.rs – uputstvo

Sajt je klon algorithm.tsp.edu.rs. U ovom folderu su:
- `codelab-demo.mbz`: demo kurs na engleskom jeziku (CODELAB-DEMO), bez korisnika;
- `codelab-demo-sr.mbz`: demo kurs na srpskom jeziku (CODELAB-DEMO-SR), bez korisnika;
- `demo.php`: skripta za prvo podešavanje (`setup`) i noćno vraćanje oba kursa (`reset`).

Dodaci su u `C:\tsp\dist`: `tiny_codelab-1.4.0.zip`, `filter_codelab-1.4.0.zip`, `qtype_codelab-1.4.0.zip`.

U putanjama ispod korisnik Virtualmin servera je `codelab`. Ako se zove drugačije, zameni ga svuda.

## 1. Provera klona (najvažnije)

U File Manager-u otvori `/home/codelab/public_html/moodle/config.php` i proveri:

| Polje | Mora biti |
|---|---|
| `$CFG->wwwroot` | `https://codelab.tsp.edu.rs` |
| `$CFG->dataroot` | `/home/codelab/moodledata` |
| `$CFG->dbname`, `$CFG->dbuser`, `$CFG->dbpass` | baza klona iz **Edit Databases** servera codelab, ne baza algoritma |

Ako ijedno polje i dalje pokazuje na algoritam, ispravi ga pre svega ostalog. U suprotnom sve što radiš na codelab-u menja algorithm sajt.

Proveri i **Server Configuration → SSL Certificate**: Let's Encrypt za `codelab.tsp.edu.rs`.

## 2. Dodaci

Otpremanje ZIP-a preko **Install plugins** na algorithm sajtu nije radilo, pa dodatke stavljaš preko File Manager-a. Svaki ZIP ima folder `codelab`:

| ZIP | Raspakuj u | Rezultat |
|---|---|---|
| `tiny_codelab-1.4.0.zip` | `public_html/moodle/public/lib/editor/tiny/plugins/` | `.../tiny/plugins/codelab` |
| `filter_codelab-1.4.0.zip` | `public_html/moodle/public/filter/` | `.../filter/codelab` |
| `qtype_codelab-1.4.0.zip` | `public_html/moodle/public/question/type/` | `.../question/type/codelab` |

Zatim otvori https://codelab.tsp.edu.rs/admin/index.php kao administrator i potvrdi nadogradnju. Moodle instalira sva tri dodatka odjednom. Stranicu sa podešavanjima CodeLab-a za sada samo sačuvaj, a vrednosti upisuje skripta.

## 3. Runner i Jobe

Runner prihvata samo sajtove navedene u `CODELAB_ORIGINS`:

1. Kao korisnik sa sudo pravom otvori `/etc/codelab-runner.env`.
2. U `CODELAB_ORIGINS` dodaj adresu demo sajta, odvojenu zarezom:
   `CODELAB_ORIGINS=https://book.tsp.edu.rs,https://codelab.tsp.edu.rs`
3. Pokreni `sudo systemctl restart codelab-runner`.

Za Jobe je bolje da demo sajt ima **svoj API ključ**. Napravi ga na Jobe serveru, tamo gde je i ključ za book. Tako se demo može isključiti bez diranja book-a, a vidi se koliko ga ljudi koriste.

## 4. Fajlovi za demo

U File Manager-u otpremi `demo.php`, `codelab-demo.mbz` i `codelab-demo-sr.mbz` u `/home/codelab/public_html/moodle`, pored `config.php`, a ne u `public`. Postojeći `demo.php` slobodno zameni.

## 5. Prvo podešavanje / ažuriranje

1. Otvori **Webmin → System → Scheduled Cron Jobs**.
2. Pokreni ručno posao (**Run Now**):
   `/usr/bin/php /home/codelab/public_html/moodle/demo.php setup`
   (ili `demo.php reset` ako je sajt već ranije podešen).
3. Na kraju izlaza treba da piše:
   `Done English: https://codelab.tsp.edu.rs/course/view.php?id=...`
   `Done Serbian: https://codelab.tsp.edu.rs/course/view.php?id=...`
4. Na naslovnoj strani se pojavljuju oba kursa i brza dugmad za engleski i srpski kurs.

Skripta radi sledeće:
- isključuje poštu i samoregistraciju i čisti HTML svuda;
- uključuje filter CodeLab;
- upisuje Jobe server `https://jobe.tsp.edu.rs` i runner `wss://runner.tsp.edu.rs`;
- uključuje Python u pregledaču i dozvoljava 60 pokretanja u minuti;
- vraća oba demo kursa iz backup-a (`CODELAB-DEMO` i `CODELAB-DEMO-SR`), sa pristupom za goste;
- pravi naloge `teacher` i `student` (lozinka `Demo-2026`) i upisuje ih u oba kursa;
- postavlja naslovnu stranu i banere sa unakrsnim vezama ka engleskom i srpskom kursu.

## 6. Ključ i tajna (ručno)

*Site administration → Plugins → Text editors → TinyMCE editor → CodeLab*:
- **Jobe API key**: ključ demo sajta (ili ključ book-a, dok ne napraviš novi);
- **Secret of the interactive server**: isti kao `CODELAB_SECRET` u `/etc/codelab-runner.env`.

Sačuvaj. Oba polja stanja moraju biti zelena: Jobe sa jezicima i runner sa c, cpp, java, mysql, csharp.

## 7. Stalni cron poslovi

| Posao | Command | Kada |
|---|---|---|
| Moodle cron | `/usr/bin/php /home/codelab/public_html/moodle/admin/cli/cron.php >/dev/null` | svakog minuta |
| Noćni reset | `/usr/bin/php /home/codelab/public_html/moodle/demo.php reset >/dev/null` | svakog dana u 3:30 |

## 8. Provera

- Otvori https://codelab.tsp.edu.rs i proveri da li postoje oba dugmeta:
  - **🚀 Open Demo Course (English)**
  - **🇷🇸 Demo kurs (srpski)**
- Proveri engleski kurs:
  - Lekcija o operatoru dodele na engleskom (C);
  - Primeri za jezike;
  - Debugger na engleskom (C i Python);
  - SQL primer sa bibliotekom (`author`, `book`) na engleskom;
  - C i C# lekcije na engleskom;
  - Kvizovi na engleskom.
- Proveri srpski kurs:
  - Sve lekcije, primeri i kvizovi na srpskom.
