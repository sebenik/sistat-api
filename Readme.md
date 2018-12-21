# SISTAT-API
### JSON API [podatkovnega portala SI-STAT](http://pxweb.stat.si/pxweb/dialog/statfile2.asp) Staističnega urada Republike Slovenije.

## About
Spletna aplikacija Statističnega urada Republike Slovenije [SI-STAT](http://pxweb.stat.si/pxweb/dialog/statfile2.asp), ki je razdeljena po tematskih področjih, omogoča iskanje in prenos objavljenih statističnih podatkov. Te je mogoče prikazati v tabeli ali prenesti v obliki različnih datotek (txt, csv, px, xls, htm).
SISTAT-API po zgledu [Eurostat-a](http://ec.europa.eu/eurostat/web/sdmx-web-services/rest-sdmx-2.1), omogoča prenos podatkov preko spletne aplikacije SI-STAT v JSON-STAT formatu. Več o formatu najdete na tej povezavi: [https://json-stat.org](https://json-stat.org).

## Uporaba
1. Na spletni strani [aplikacije SI-STAT](http://pxweb.stat.si/pxweb/dialog/statfile2.asp) poiščete področje in sklop podatkov, ki jih želite uporabiti.
2. Sestavite API klic:
    * __URL__ - [http://sistat.sebenik.com/](http://sistat.sebenik.com/)
    * __GET parametri:__
      * __ma__ - parameter, katerega vrednost najdete v spletnem naslovu izbranega sklopa podatkov (obvezen parameter)
      * __path__ - parameter, katerega vrednost najdete v spletnem naslovu izbranega sklopa podatkov (obvezen parameter)
      * __dimenzija*__ - parametri s katerimi označimo izbrane dimenzije in vrednosti v tabelah. Ime parametra pišemo z malimi črkami, presledke pa nadomestimo s podčrtajem '\_'. Vrednosti posameznega parametra pišemo kakor so prikazane v tabelah na spletni strani. V primru izbire večjega števila vrednosti posamezne dimenzije, le te ločimo z vejico ','. Če želimo izbrati vse vrednosti posamezne dimenzije lahko namesto naševanja vseh, za vrednost dimenzije napišemo _'all'_, če želimo izbrati zgolj prvo vrednost dimenzije lahko napišemo _'first'_ ali _'last'_, če želimo izbrati zgolj zadnjo vrednost dimenzije. (Glej primer.)
      Dimenzije, ki so v spletni aplikaciji označene z zvezdico, so obvezne.
3. Struktura odgovora je v [JSON-STAT](https://json-stat.org) formatu. Vsebuje naslednja polja:
    * __version__ - označuje vezijo JSON-STAT formata ([ref](https://json-stat.org/format/#version))
    * __class__ - označuje razred odgovora. Odgovor SISTAT-API-ja je vedno razreda 'dataset'. ([ref](https://json-stat.org/format/#class))
    * __title__ - naslov tabele oz. sklopa prenešenih podatkov.
    * __description__ - opis tabele oz. sklopa prenešenih podatkov.
    * __contents__ - področje (nadskupina) sklopa prenešenih podatkov.
    * __created__ - označuje čas generiranja podatkov 'leto/mesec/dan ura'.
    * __updated__ - označuje čas posodobitve podatkov 'leto/mesec/dan ura'.
    * __source__ - vir podatkov.
    * __href__ - spletni naslov do spletne aplikacije za konkretni sklop prenešenih podatkov.
    * __id__ - polje vsebuje oznake izbranih dimenzij. ([ref](https://json-stat.org/format/#id))
    * __size__ - polje vsebuje število izbranih vrednosti za posamezno dimenzijo. Zaporedje je enako kot v polju 'id'. ([ref](https://json-stat.org/format/#size))
    * __dimension__ - objekt vsebuje informacije o posamezni izbrani dimenziji. ([ref](https://json-stat.org/format/#dimension))
        * __label__ - ime dimenzije. ([ref](https://json-stat.org/format/#label))
        * __category__ - objekt opisuje možne vrednosti dimenzije. ([ref](https://json-stat.org/format/#category))
            * __index__ - objekt vsebuje vrstni red možnih vrednosti dimenzije. ([ref](https://json-stat.org/format/#index))
            * __label__ - objekt vsebuje imena možnih vrednosti dimenzije. ([ref](https://json-stat.org/format/#label))
            * __note__ - objekt vsebuje morebitna dodatna pojasnila za posamezno vrednost dimenzije. ([ref](https://json-stat.org/format/#note))
    * __value__ - objekt vsebuje podatke glede na izbrane dimenzije. ([ref](https://json-stat.org/format/#value))
    * __note__ - polje vsebuje morebitna dodatna pojasnila o prenešenih podatkih ter kontakt. ([ref](https://json-stat.org/format/#note))

## Primer
Primer za podatke: __[Sklenitve zakonskih zvez po starostnih skupinah ženina in neveste, statistične regije, Slovenija, letno](https://pxweb.stat.si/pxweb/Dialog/varval.asp?ma=05M2008S&ti=&path=../Database/Dem_soc/05_prebivalstvo/34_Poroke/10_05M20_poroke-RE-OBC/&lang=2)__

1. Iz spletnega naslova za izbran sklop podatkov [https://pxweb.stat.si/pxweb/Dialog/varval.asp?ma=05M2008S&ti=&path=../Database/Dem_soc/05_prebivalstvo/34_Poroke/10_05M20_poroke-RE-OBC/&lang=2](https://pxweb.stat.si/pxweb/Dialog/varval.asp?ma=05M2008S&ti=&path=../Database/Dem_soc/05_prebivalstvo/34_Poroke/10_05M20_poroke-RE-OBC/&lang=2) prekopiramo GET parametra __ma__ in __path__.
    * __ma__=05M2008S
    * __path__=../Database/Dem_soc/05_prebivalstvo/34_Poroke/10_05M20_poroke-RE-OBC/
2. Ostale GET parametre za poizvedbo kot je prikazana na spodnji sliki zapišemo sledeče:
![primer izbire parametrov](http://i.imgur.com/nIDFSSk.png)
    * __meritve__=all
    * __statistična_regija__=all
    * __leto__=last
    * __starost__=20-24 let,25-29 let,30-34 let
3. Celoten GET request izgleda tako:
  [http://sistat.sebenik.com/?ma=05M2008S&path=../Database/Dem_soc/05_prebivalstvo/34_Poroke/10_05M20_poroke-RE-OBC&meritve=all&statisti%C4%8Dna_regija=all&leto=last&starost=20-24%20let,25-29%20let,30-34%20let](http://sistat.sebenik.com/?ma=05M2008S&path=../Database/Dem_soc/05_prebivalstvo/34_Poroke/10_05M20_poroke-RE-OBC&meritve=all&statisti%C4%8Dna_regija=all&leto=last&starost=20-24%20let,25-29%20let,30-34%20let)


## Licenca
* Podatki: potrebno je navesti vir podatkov, ki jih uporabljate (Statistični urad Republike Slovenije)
* SISTAT-API: [MIT License](https://github.com/sebenik/sistat-api/blob/master/LICENSE)

## Avtor
Žiga Šebenik [ziga@sebenik.com](mailto:ziga@sebenik.com)
