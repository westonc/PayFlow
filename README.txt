Initial-stage rewrite of:

http://code.google.com/p/paypal-payflowpro-php/

which I found basically useful as a starting point, but also had the following problems:

* Lots of repeated code that would be clearer and better isolated if factored into functions -- chiefly, the part setting up the curl headers and building the request body (which is around 50 lines of code) is repeated for each of the 5 different card transaction types.

* I found the argument structure/requirements for the various methods a little confusing and hard to keep track of. I thought it might work better to separate things into two transactions -- a class representing a credit card "transaction slip" and a PayFlow class that serves as abstraction over the PayFlow HTTPS API.

It's not well documented yet, I may have broken genuinely useful things from the earlier version of the SDK that I'm rewriting because I didn't understand them, YMMV, YHBW.
