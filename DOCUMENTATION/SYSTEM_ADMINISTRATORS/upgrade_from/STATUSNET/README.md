Upgrading
=========

GNU social 1.1.x to GNU social 1.2.x
------------------------------------

If you are tracking the GNU social git repository, we currently recommend
using the "master" branch (or nightly if you want to use latest features)
and follow this procedure: 

0. Backup your data. The StatusNet upgrade discussions below have some
    guidelines to back up the database and files (mysqldump and rsync).

    MAKE SURE YOU ARE THE SAME USER THAT RUNS THE PHP FILES WHILE PERFORMING
    THE COMMANDS BELOW (I usually prepend the commands with 'sudo -u social')

1. Stop your queue daemons (you can run this command even if you do not
    use the queue daemons):
    $ bash scripts/stopdaemons.sh

2. Run the command to fetch the latest sourcecode:
    $ git pull
    
    If you are not using git we recommend following the instructions below
    for upgrading "StatusNet 1.1.x to GNU social 1.2.x" as they are similar.

3. Run the upgrade script:
    $ php scripts/upgrade.php

   The upgrade script will likely take a long time because it will
    upgrade the tables to another character encoding and make other
    automated upgrades. Make sure it ends without errors. If you get
    errors, create a new task on https://git.gnu.io/gnu/gnu-social/issues

4. Start your queue daemons again (you can run this command even if you
    do not use the queue daemons):
    $ bash scripts/startdaemons.sh

5. Report any issues at https://git.gnu.io/gnu/gnu-social/issues

If you are using ssh keys to log in to your server, you can make this
procedure pretty painless (assuming you have automated backups already).
Make sure you "cd" into the correct directory (in this case "htdocs")
and use the correct login@hostname combo:
    $ ssh social@domain.example 'cd htdocs
            && bash scripts/stopdaemons.sh
            && git pull
            && time php scripts/upgrade.php
            && bash scripts/startdaemons.sh'

StatusNet 1.1.x to GNU social 1.2.x
-----------------------------------

We cannot support migrating from any other version of StatusNet than 
1.1.1. If you are running a StatusNet version lower than this, please 
follow the upgrade procedures for each respective StatusNet version.

You are now running StatusNet 1.1.1 and want to migrate to GNU social
1.2.x. Beware there may be changes in minimum required version of PHP
and the modules required, so review the INSTALL file (php5-intl is a
newly added dependency for example).

* Before you begin: Make backups. Always make backups. Of your entire 
directory structure and the database too. All tables. All data. Alles.

0. Make a backup of everything. To backup the database, you can use a
variant of this command (you will be prompted for the database password):
    $ mysqldump -u dbuser -p dbname > social-backup.sql

1. Stop your queue daemons 'bash scripts/stopdaemons.sh' should do it.
    Not everyone runs queue daemons, but the above command won't hurt.

2. Unpack your GNU social code to a fresh directory. You can do this
    by cloning our git repository:
    $ git clone https://git.gnu.io/gnu/gnu-social.git gnusocial

3. Synchronize your local files to the GNU social directory. These 
    will be the local files such as avatars, config and files:

        avatar/*
        file/*
        local/*
        .htaccess
        config.php

    This command will point you in the right direction on how to do it:
    $ rsync -avP statusnet/{.htaccess,avatar,file,local,config.php} gnusocial/

4. Replace your old StatusNet directory with the new GNU social
    directory in your webserver root.

5. Run the upgrade script: 'php scripts/upgrade.php'
   The upgrade script will likely take a long time because it will
    upgrade the tables to another character encoding and make other
    automated upgrades. Make sure it ends without errors. If you get
    errors, create a new task on https://git.gnu.io/gnu/gnu-social/issues

6. Start your queue daemons: 'bash scripts/startdaemons.sh'

7. Report any issues at https://git.gnu.io/gnu/gnu-social/issues
