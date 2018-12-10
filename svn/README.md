# SVN Instructions

This file demonstrates how to upload the module to Woocommerce market.

## Upload

1- Clone svn repository using this command:
```
svn checkout http://plugins.svn.wordpress.org/pagamastarde svn/
```

2- Move the current code to the current tag version
```
mkdir svn/tags/<current_version>
cp -pr svn/trunk/ svn/tags/<current_version> 
```

3- Remove old trunk
```
rm -rf svn/trunk/
```

4- Copy new files to trunk, overwriting the old one.
```
cp -pr assets/ svn/trunk/assets/
cp -pr controllers/ svn/trunk/controllers/
cp -pr includes/ svn/trunk/includes/
cp -pr languages/ svn/trunk/languages/
cp -pr templates/ svn/trunk/templates/
cp -pr vendor/ svn/trunk/vendor/
cp readme.txt svn/trunk/readme.txt
cp WC_Paylater.php svn/trunk/WC_paylater.php

``` 

4-Add the files to svn track
```
svn add -f svn/.
```

5- Commit the files to svn
```
svn commit -m "<new_version>"
```  