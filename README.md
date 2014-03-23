#eXtplorer ISPConfig authentification
This authetification plugin allows you to authentificated against the ISPConfig sys_user table. 

So just surf foo.bar/extplorer enter your ISPConfig credentials and if this sites belongs to your ISPConfig user you are in ;)


##Usage
###Install eXtplore
First you need to download and install eXtplore itself.
Get it here: http://extplorer.net/ and upack it to e.g. /usr/local/share/extplorer or similar.

###Install the plugin
Download the plugin and copy ispconfig.php to eXtplorer's include/authentifcation directory.

###Configure the plugin
Within config/conf.php of eXtplorer you need to:

####1. Add the plugin to the list of allowed authentification methods
 ```
$GLOBALS['ext_conf']['authentication_methods_allowed'] = array('ispconfig', 'extplorer', 'ftp');
 ```
####2. Set it as as default authentification method if needed
```
$GLOBALS['ext_conf']['authentication_method_default'] = 'ispconfig';
```
####3. Configure the connection to ISPconfig database
```
$GLOBALS['ext_conf']['ispconfig'] = array(
	'dbUser' => 'ispconfig',
	'dbPassword' => 'xxxx',
	'dbHost' => 'localhost',
	'dbSchema' => 'dbispconfig'
);
```

###Configure Webserver
In order to make eXtplorer availabe for all websites you need to set an alias. For apache running on debian this can be done by adding a file called extplorer.conf to /etc/apache2/conf.d/:
```

Alias /extplorer /usr/local/share/extplorer

<Directory /usr/local/share/extplorer>
        Order deny,allow
        Allow from all
        Options FollowSymLinks
        DirectoryIndex index.php
</Directory>

```

Done :)
