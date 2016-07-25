# SISTAT-API
### JSON API [podatkovnega portala SI-STAT](http://pxweb.stat.si/pxweb/dialog/statfile2.asp) Staističnega urada Republike Slovenije.

## About
Spletna aplikacija Statističnega urada Republike Slovenije SI-STAT, ki je razdeljena po tematskih področjih, omogoča iskanje in prenos objavljenih statističnih podatkov. Te je mogoče prikazati v tabeli ali prenesti v obliki različnih datotek (txt, csv, px, xls, htm).
SISTAT-API po zgledu [Eurostat-a](http://ec.europa.eu/eurostat/web/sdmx-web-services/rest-sdmx-2.1), omogoča prenos podatkov preko spletne aplikacije SI-STAT v JSON-STAT formatu. Več o formatu najdete na tej povezavi: [https://json-stat.org](https://json-stat.org).

## Uporaba
1. Na spletni strani [aplikacije SI-STAT](http://pxweb.stat.si/pxweb/dialog/statfile2.asp) poiščete področje in sklop podatkov, ki jih želite uporabiti.
2. Sestavite API klic:
    * __ma__ - parameter, ki ga najdete v spletnem naslovu tabele (obvezen parameter)
    * __path__ - parameter, ki ga najdete v spletnem naslovu tabele (obvezen parameter)

## Primeri

## Licenca

## Avtor
Žiga Šebenik [ziga@sebenik.com](mailto:ziga@sebenik.com)
