=== Backup Migration ===
Contributors: Migrate
Tags: Migration, Backup, Staging, Migrate, Backups
Requires at least: 4.6
Tested up to: 6.8.2
Stable tag: 1.4.9.1
License: GPLv3
Requires PHP: 5.6

Backup Migration

== Description ==

**Try it out on your free dummy site: Click here => [https://tastewp.com/plugins/backup-backup](https://demo.tastewp.com/bmi).**
(this trick works for all plugins in the WP repo - just replace "wordpress" with "tastewp" in the URL)

Creating a backup of your site has never been easier!

Simply install the plugin, click on "Create backup now" - done.

You can also schedule backups, e.g. define that a backup should be taken automatically every week (or every day/month).

Use a wide choice of configuration options:

- Define exactly which files / databases should be in the backup, and which not
- Define where the backup will be stored (as of now, only a local option is available, but we'll expand this soon)
- Define what name your backup should have, in which instances you should receive a notification email, and much more

This plugin is all in one solution if you need to migrate your site to another host or just restore the local backup.

Note: This (free) version is limited to backups of 4GB in size, due to native WordPress ZIP limitations.. For unlimited sizes and increased stability for larger sites, please have a look at the [Premium Plugin](https://backupbliss.com). The code of this free plugin is licensed under [GPLv3](https://www.gnu.org/licenses/gpl-3.0.en.html), however, we claim rights to other content. Please read the full [Terms of Use](https://backupbliss.com/terms) that touch other points as well and apply in entirety.

If any questions come up, please ask us in the [Support Forum](https://wordpress.org/support/plugin/backup-backup) - we're always happy to help!

== Frequently Asked Questions ==

= How do I create my first backup? =

Click on “Create backup now” on the settings page of the BackupBliss - Backup Migration Staging plugin.

BackupBliss - Backup Migration Staging will by default create a backup that contains everything from your site, except the BackupBliss plugin’s own backups and WordPress installation - if you want to include the WordPress installation as well, tick the checkbox in the section “What will be backed up?”.

You can download backup or migrate your backup (use the plugin as a WordPress duplicator) immediately after the backup has been created.

= How do I restore a backup? =

- If your backup is **located on your site**: Go to the BackupBliss Backup Migration Staging plugin screen, then to the Manage & Restore Backup(s) tab where you have your backups list, click on the Restore button next to the backup you would like to restore.

- If your backup is **located on another site**: Go to the BackupBliss - Backup Migration Staging plugin screen on site #1, then to the Manage & Restore Backup(s) tab where you have the backups list, click on the “Copy Link”-button in the “Actions”-column. Go to the BackupBliss - Backup Migration Staging plugin screen on site #2, then to the Manage & Restore Backup(s) tab, click on “Super-quick migration”, paste the copied link, and hit the “Restore now!” button. This process will first import the backup and then restore it, i.e. Backup Migrate also serves as backup importer.

- If your backup is *located on another device*: Go to the BackupBliss - Backup Migration Staging plugin screen, then to the Manage & Restore Backup(s) tab, and click on the “Upload backup files” button. After the upload, click on the Restore button next to the backup you would like to restore.

- If your backup is *located on Google Drive, OneDrive, Dropbox, BackupBliss Storage, FTP or SFTP*: Go to the BackupBliss - Backup Migration Staging plugin screen, then to the plugin section “Where shall the backup(s) be stored?”, turn ON the respective external storage option, and connect to your account. After that, the plugin will sync the available backup files in the plugin section “Manage & Restore Backups” from where you will be able to run Restore.

= How do I migrate or clone my site? =

Migrate (or clone) a WordPress site by creating a full backup on the site that you want to migrate (clone) - site #1.

- To transfer website **directly from site #1 to site #2**: Go to the BackupBliss - Backup Migration Staging plugin screen on site #1, then to the Manage & Restore Backup(s) tab where you have the backups list, click on the Copy Link button in the Actions column. Go to the BackupBliss - Backup Migration Staging plugin screen on site #2, then to the Manage & Restore Backup(s) tab, click on “Super-quick migration”, paste the copied link, and hit the “Restore now!” button. Make sure that the backup file on site #1 is accessible by setting “Accessible via direct link?” to “Yes” in the plugin section “Where shall the backup(s) be stored?”

- To migrate the website **indirectly**: Go to the BackupBliss - Backup Migration Staging plugin screen, then to the Manage & Restore Backup(s) tab, and click on the “Upload backup files” button. After the upload, click on the Restore button next to the backup you would like to restore.

- To migrate the website with *Google Drive, OneDrive, Dropbox, BackupBliss Storage, FTP or SFTP*: Go to the BackupBliss - Backup Migration Staging plugin screen, then to the plugin section “Where shall the backup(s) be stored?”, turn ON the respective external storage option, and connect to your account. After that, the plugin will sync the available backup files in the plugin section “Manage & Restore Backups” from where you will be able to run Restore.

= Where can I find my backups? =

BackupBliss - Backup Migration Staging allows you to download backups, migrate backups, or delete backups directly from the plugin screen Manage & Restore Backup(s). By default, the migrator plugin will store backups locally on the server to /wordpress/wp-content/backup-migration but you can change the backup location to anywhere you please. If you have backups stored on the cloud - OneDrive, Dropbox, Google Drive, BackupBliss Storage, FTP or SFTP, you will need to connect the plugin with the respective storage account, so that the plugin can synchronize the data.

= How to run automatic backups? =

Enabling automatic backups is done on the BackupBliss - Backup Migration Staging plugin’s home screen, just next to the “Create backup now!” button. Auto backup can run on a monthly, weekly, or daily basis. You can set the exact time (and day) and how many automatic backups would you like to keep in the same BackupBliss - Backup Migration Staging plugin section. We recommend that you optimize the number of backups that will be kept according to available space.

= How big are backup files? =

Backup file size depends on the criteria you select in the “What will be backed up?” section of the BackupBliss - Backup Migration Staging plugin. There you can see file/folder size calculations as you Save your settings. Usually, WordPress’ Uploads folder is the heaviest, while Databases are the lightest. If you are looking to save up space, you might want to deselect Plugins and WordPress installation folders, as you can usually download those anytime from WP sources.

= Is the backup creation and site migration free? =

Yes. You can create full site backups, and automatic backups, and migrate your site (duplicate site) free of charge. [BackupBliss - Backup Migration Staging Pro](https://sellcodes.com/oZxnXtc2) provides more sophisticated filters and selections of files that will be included/excluded from backups (affecting backup size), faster backup creation times, number of external backup storage locations, backup encryption, backup file compression methods, advanced backup triggers, additional backup notifications by email, priority support, and more.

= ⭐️ NEW! How to create staging sites? =

You can easily set up a staging environment for your website with the BackupBliss plugin. You can choose to create a staging site either on your server / machine or on [TasteWP](https://tastewp.com/). Both options are free!

1. To create a staging site on your server, navigate to the plugin section “Create a staging site”, select “Your server & domain”, define a custom path if you wish, and click on the button “Create staging site!”.

2. To create a stage site on a free WordPress sandbox platform - [TasteWP](https://tastewp.com/), select the option “TasteWP (external server)”, then select a backup file that will be used, and click on the button “Create staging site!”.

= ⭐️ NEW! Is cloud backup available? =

Backup to Google Drive, OneDrive, FTP, BackupBliss Storage, SFTP and Dropbox are now available in the [BackupBliss - Backup Migration Staging Pro](https://sellcodes.com/oZxnXtc2)
Upcoming storage options will include: Rackspace, DreamObjects, OpenStack, Google Cloud, Microsoft Azure, Backblaze, and more.

= ⭐️ NEW! How do I back up to Google Drive / OneDrive / BackupBliss Storage / Dropbox / FTP / SFTP? =

In order to automatically upload your site backups to the Cloud, you will need a [Pro version](https://sellcodes.com/oZxnXtc2) of the plugin. Once installed and activated, navigate to the plugin section “Where shall the backup(s) be stored?”, and turn ON the respective external storage feature. Click on the button Connect, and select an account you want to connect to. Once it is connected, your backup files from the website will start to sync to your connected storage. You can monitor the process in the plugin section “Manage & Restore Backups”

= How are you better than other backup/migration plugins?  =

Besides having the most intuitive interface and smoothest user experience, BackupBliss - Backup Migration Staging plugin will always strive to give you more than any competitor:
- Updraftplus: They charge for migration, with our plugin it's free;
- All-in-One WP Migration: In the free version, compared to our plugin - they don’t have selective/partial backups; they lack advanced options and each external storage is on a separate extension plugin; they have no automatic backups;
- Duplicator: In the free version, compared to our plugin - they have no selective backups, exclusion rules, no automatic backups and no migration;
- WPvivid: In the free version, compared to our plugin - they don’t have selective/partial backups, exclusion rules, or automatic backups;
- BackWPup: In the free version, compared to our plugin - they lack restore options, backups are slower, automatic backups are dependant on wp cron;
- Backup Guard:  In the free version, compared to our plugin - they have no selective backups, exclusion rules; no direct migration;
- XCloner: Automatic backups are dependant on wp cron; full restore not available on a local server;
- Total Upkeep: They lack the advanced selective backups and exclusion rules, lacks a monthly backup schedule

= How to upload my backup file? =

Uploading a backup can be simply done by navigating to the Manage & Restore Backup(s) section of the BM plugin (tab on the right side). There you have the “Upload backup file” button, after clicking on it, you need to select a proper backup that is made by this plugin only. You cannot use backups from other plugins (to restore those, go back to those plugins and restore them this way). If you use “Super-quick migration” (section b), your backup will be automatically uploaded. If you are having trouble uploading the backup file, go bac and ensure that the folder designated for backups is writable. You can find the backup destination in the plugin section “Where shall the backup(s) be stored?

= Is the plugin also available in my language? =

So far we have translated the plugin into these languages:

Arabic: [إنشاء نسخة احتياطية واستعادة النسخ الاحتياطية وترحيل المواقع. أفضل مكون إضافي لمواقع الترحيل والاستنساخ!](https://ar.wordpress.org/plugins/backup-backup/)
Chinese (China): [创建备份、还原备份和迁移站点。 迁移和克隆网站的最佳插件！](https://cn.wordpress.org/plugins/backup-backup/)
Croatian: [Izradite sigurnosnu kopiju, vratite sigurnosne kopije i migrirajte web-mjesta. Najbolji dodatak za migraciju i kloniranje web stranica!](https://hr.wordpress.org/plugins/backup-backup/)
Dutch: [Maak back-ups, herstel back-ups en migreer sites. De beste plug-in voor het migreren en klonen van websites!](https://nl.wordpress.org/plugins/backup-backup/)
English: [Create a backup, restore backups and migrate a website. The best plugin for migration and to clone a website](https://wordpress.org/plugins/backup-backup/)
Finnish: [Luo varmuuskopio, palauta varmuuskopiot ja siirrä sivustot. Paras laajennus sivustojen siirtoon ja kloonaukseen!](https://fi.wordpress.org/plugins/backup-backup/)
French (France): [Créez des sauvegardes, restaurez des sauvegardes et migrez des sites. Le meilleur plugin pour les sites Web de migration et de clonage !](https://fr.wordpress.org/plugins/backup-backup/)
German: [Erstellen Sie Backups, stellen Sie Backups wieder her und migrieren Sie Websites. Das beste Plugin für Migrations- und Klon-Websites!](https://de.wordpress.org/plugins/backup-backup/)
Greek: [Δημιουργία αντιγράφων ασφαλείας, επαναφορά αντιγράφων ασφαλείας και μετεγκατάσταση τοποθεσιών. Το καλύτερο πρόσθετο για μετανάστευση και κλωνοποίηση ιστοσελίδων!](https://el.wordpress.org/plugins/backup-backup/)
Hungarian: [Biztonsági másolat készítése, biztonsági másolatok visszaállítása és webhelyek migrálása. A legjobb bővítmény a webhelyek migrációjához és klónozásához!](https://hu.wordpress.org/plugins/backup-backup/)
Indonesian: [Buat cadangan, pulihkan cadangan, dan migrasikan situs. Plugin terbaik untuk migrasi dan kloning situs web!](https://id.wordpress.org/plugins/backup-backup/)
Italian: [Crea backup, ripristina backup e migra i siti. Il miglior plugin per la migrazione e la clonazione di siti web!](https://it.wordpress.org/plugins/backup-backup/)
Persian: [ایجاد نسخه پشتیبان، بازیابی نسخه پشتیبان، و مهاجرت سایت ها. بهترین افزونه برای مهاجرت و شبیه سازی وب سایت ها!](https://fa.wordpress.org/plugins/backup-backup/)
Polish: [Twórz kopie zapasowe, przywracaj kopie zapasowe i przenoś witryny. Najlepsza wtyczka do migracji i klonowania stron internetowych!](https://pl.wordpress.org/plugins/backup-backup/)
Portuguese (Brazil): [Crie backup, restaure backups e migre sites. O melhor plugin para migração e clonagem de sites!](https://br.wordpress.org/plugins/backup-backup/)
Russian: [Создавайте резервные копии, восстанавливайте резервные копии и переносите сайты. Лучший плагин для миграции и клонирования сайтов!](https://ru.wordpress.org/plugins/backup-backup/)
Spanish: [Cree copias de seguridad, restaure copias de seguridad y migre sitios. ¡El mejor complemento para sitios web de migración y clonación!](https://es.wordpress.org/plugins/backup-backup/)
Turkish: [Yedekleme oluşturun, yedeklemeleri geri yükleyin ve site taşıyın. Websitesi taşımaya ve klonlamaya yönelik en iyi eklentidir!](https://tr.wordpress.org/plugins/backup-backup/)
Vietnamese: [Tạo sao lưu, khôi phục các bản sao lưu và di chuyển các trang web. Plugin tốt nhất để di chuyển và sao chép các trang web!](https://vi.wordpress.org/plugins/backup-backup/)

== Screenshots ==
1. Backup Migration plugin front
2. What will be backed up
3. Backup in progress
4. Backup finished
5. Manage & Restore backups
6. Restoring in progress
7. Restore finished
8. Staging Sites

== Installation ==

= Admin Installer via search =
1. Visit the Add New plugin screen and select "Author" from the dropdown near search input
2. Search for "Migrate"
3. Find "Backup Migration" and click the "Install Now" button.
4. Activate the plugin.
5. The plugin should be shown below settings menu.

= Admin Installer via zip =
1. Visit the Add New plugin screen and click the "Upload Plugin" button.
2. Click the "Browse..." button and select the zip file of our plugin.
3. Click "Install Now" button.
4. Once uploading is done, activate Backup Migration.
5. The plugin should be shown below the settings menu.

== Changelog ==
= 1.4.9 =
* Tested with WordPress 6.8.2
* [FEATURE] Introducing BackupBliss Storage as new cloud storage option for FREE.
* [FEATURE] Added security plugins warning module and logic
* [MISC] Minor grammar and spelling corrections.
* [ENHANCEMENT] Detailed explanation on Automatic Backups cron configuration.
* [ENHANCEMENT] Improved free space checking logic during migration process
* [ENHANCEMENT] Detailed space checking messages for backup and restore process
* [FIX] Graphical glitches and issues in certain areas of the plugin.
* [ENHANCEMENT] Directory path validation for local storage.
* [FIX] Disable auto complete on certain fields to avoid unnecessary auto filling done by browser.
* [FIX] Prevent duplicate email notifications for automatic backup delays
* [ENHANCEMENT] makeNewLoginSession to validate user ID and manage login sessions
* [ENHANCEMENT] Detailed logging for cron based backup creation
* [FIX] Improved log handling for large files in global logs
* Serveral other enhancements and bug fixes throughout the plugin

= 1.4.8 =
* Tested with WordPress 6.7.2
* [FIX] Added functionality to support both uploading and downloading of .tar.gz and .tar backups.
* [FIX] Updated space check logic to prevent false positive 'Not enough space' alerts.
* [FIX] Prevent deletion of partially downloaded backup during Super-quick migration
* [FIX] Super-quick migration using cli
* [FIX] Fixed an issue where restoration process is stuck due to an infinite recursion in unserialize replacement

= 1.4.7 =
* Tested with WordPress 6.7.1
* [FEATURE] Backup size limit has been bumped to 4GB (previously it was 2GB).
* [FIX] Fixed an issue with restore failing when it reaches DB restoring setup.
* [FIX] Fixed a restoration issue with the same file being moved multiple times.
* [FIX] Bug fix which prevented the CLI method from being used due to a lock file logic.
* [FIX] Bug fix related to early loading of translation (https://core.trac.wordpress.org/changeset/59127).
* [FIX] Fixed a security issue in the backup restore feature.
* [ENHANCEMENT] Enhancements to compatibility notices and "not enough space" error handling.
* [ENHANCEMENT] Enhanced Debug-It-Yourself notice when an issue occurs.
* [ENHANCEMENT] Updated incompatible plugins list (which are known to interfere with the working of the plugin).
* [ENHANCEMENT] Clear indication of space issues (if any) when backup/restore fails.
* [ENHANCEMENT] Minor adjustments to cloud storage listing.
* [ENHANCEMENT] Optimization of backups list fetching.
* [ENHANCEMENT] Enhanced backup file path handling for core and content files.
* [ENHANCEMENT] Enhanced restore progress modal with dynamic titles and warnings based on the restore process state.
* [ENHANCEMENT] Enhanced backup file handling and cleanup logic (after backup errors).

= 1.4.6 =
* Tested with WordPress 6.6
* Minor performance improvements
* Improvements for PHP 8 utilization

= 1.4.5 =
* [FIX] Resolved issues with cURL backup method and false positive errors
* [FIX] Fixed issues with small size backups (<=~10MB)
* [FIX] Prevented errors during space check
* [FIX] Fixed issues with database backup

= 1.4.4 =
* [NEW] Added "error translator" which tells the user what went wrong based on the live log - it will be extended during time
* [NEW] Added size checks, if the batch decreased the ZIP size it will consider it as damaged and return error
* [NEW] Backups list will now load until it can process all backups (for sites with low max execution time)
* [NEW] Added support for flexible ZipArchive which will be prioritized over PCLZip (works out of the box)
* [NEW] Our plugin will now ignore search replace on large tables that are known to be huge log tables
* [... and more ...]

= previous =
Old changelog has been removed due to WordPress limitation of 5000 characters.

== Upgrade Notice ==

= 1.4.9 =
What's new in 1.4.9?
* Tested with WordPress 6.8.2
* [FEATURE] Introducing BackupBliss Storage as new cloud storage option for FREE.
* [FEATURE] Added security plugins warning module and logic
* [MISC] Minor grammar and spelling corrections.
* [ENHANCEMENT] Detailed explanation on Automatic Backups cron configuration.
* [ENHANCEMENT] Improved free space checking logic during migration process
* [ENHANCEMENT] Detailed space checking messages for backup and restore process
* [FIX] Graphical glitches and issues in certain areas of the plugin.
* [ENHANCEMENT] Directory path validation for local storage.
* [FIX] Disable auto complete on certain fields to avoid unnecessary auto filling done by browser.
* [FIX] Prevent duplicate email notifications for automatic backup delays
* [ENHANCEMENT] makeNewLoginSession to validate user ID and manage login sessions
* [ENHANCEMENT] Detailed logging for cron based backup creation
* [FIX] Improved log handling for large files in global logs
* Serveral other enhancements and bug fixes throughout the plugin