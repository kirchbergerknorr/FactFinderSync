Kirchbergerknorr FactFinderSync
===============================

FactFinderSync module is designed to synchronize Magento Products with FactFinder database using WSDL.

Installation
------------

1. Add `require` and `repositories` sections to your composer.json as shown in example below and run `composer update`.
2. Module will be installed `flagbit/factfinder` first as composer dependency.
3. Module creates datetime catalog product attribute `factfinder_updated`.
4. Configure and activate FactFinder.
5. Configure options in in System -> Configuration -> Kirchbergerknorr -> FactFinderSync. 
6. Set `Max products queue size for processing` to limit products count per each transaction.
7. Actiavate logs and module.


*composer.json example*

```
{
    ...
    
    "repositories": [
        {"type": "git", "url": "https://github.com/kirchbergerknorr/factfindersync"},
    ],
    
    "require": {
        "kirchbergerknorr/factfindersync": "*"
    },
    
    ...
}
```

Workflow
--------

1. Products with empty `factfinder_updated` attribute are exporting to FactFinder using `insertProducts` WSDL method.
2. Attribute `factfinder_updated` updating to current datetime.
3. Products with `factfinder_updated` < `updated_at` are exporting to FactFinder using `updateProducts` WSDL method. 

Support
-------

Please [report new bugs](https://github.com/kirchbergerknorr/factfindersync/issues/new).

What new in 1.0.0?
------------------

- [x] Products with empty `factfinder_updated` attribute are exporting to FactFinder using `insertProducts` WSDL method.
- [x] Attribute `factfinder_updated` updating to current datetime.
- [x] Products with `factfinder_updated` < `updated_at` are exporting to FactFinder using `updateProducts` WSDL method. 
