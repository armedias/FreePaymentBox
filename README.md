# FreePaymentBox


This is module to use Paybox Payment system for Prestashop.

Module for using Paybox in Prestashop 1.5.

No CGI module required, instead it use the HMAC verification method.

It already works on a real shop with real money but need improvements (admin part at least)

## Requirements

- php (version ?)
- openssl
- a test or a real customer account (provided by paybox) 
- prestashop >= 1.5.2

Documentation, test account and public key are provided by Paybox and are needed. Those are supplied in Paybox download section.

## Todo

Admin part of the module is pretty ugly at this time
- mode_prod : 0 is for test, 1 is for production
- pbx_hash : is to be set to SHA512


## DISCLAIMER

This module is NOT made by Paybox and has no relation with Point Transaction Systems. 
Prestashop is NOT involved in this project.

Paybox, Point Transaction Systems and Prestashop are registred trademarks.

This code provided as NO WARRANTY and is published under the ??? License.



