# Change Log

## [Unreleased]

### Updateinfo
- **[API]** Der Zugriff auf Moodle erfolgt über die REST API anstatt der SOAP API. Hierzu muss das entsprechende Protokoll im Moodle aktiviert werden.
- **[API]** Die folgenden neuen Webservice Funktionen müssen hinzugefügt werden:
	* core_role_assign_roles
	* core_course_get_courses_by_field
	* core_webservice_get_site_info
	* enrol_manual_unenrol_users
- **[CORE]** Es wurde ein neues Config File für die Konfiguration des Addons erstellt. Dieses liegt jetzt unter /config/config.php.ex
	Die Datei muss auf config.php kopiert und entsprechend angepasst werden.
- **[MOODLE]** Es wurde eine neue Version des Moodle Plugins hinzugefügt. Diese befindet sich unter /system/moodlePlugin/ muss im Moodle installiert werden

### Added
- **[CORE]** Moodle Kurse können mit Spezialgruppen verknüpft werden. 
	Die Benutzer in dieser Gruppe werden automatisch zu dem Moodle Kurs hinzugefügt und auch automatisch aus dem Moodle Kurs entfernt wenn diese nicht mehr in der Gruppe enthalten sind.
- **[MOODLE]** Leiter von Abteilungen können automatisch zu Kursen zugeteilt werden. 
	* Leiter der Organisationseinheiten des Kurses werden direkt zu den Kursen zugeordent
	* Leiter / Stellvertreter / Assistenz des Studiengangs werden zu der Kurskategorie zugeordnet.
- **[CORE]** Neue User können automatisch über einen Cronjob erstellt werden. Dadurch wird die Kurserstellung aus dem CIS heraus beschleunigt da die User bereits im Moodle existieren.
- **[CORE]** Studierende bei denen die Lehrveranstaltung angerechnet ist können mit einer eigenen Rolle in den Kurs eingeschrieben werden um diese von regulären Studierenden zu unterscheiden

### CHANGED
- **[CIS]** Die manuelle Kurserstellung im CIS zeigt 

### Removed
- **[CORE]** Der Zugriff über die SOAP Schnittstelle wurde entfernt und durch REST ersetzt

