##Mark as deprecated all the variables that has a corresponding entry in the deprecated CSV file

Before executing the script, is highly recommended to duplicate the language file that is wanted to modify.

This script takes a language file and adds a comment "###deprecated" at the end of each line that has a corresponding entry in
the "lang_deprecated_leg.csv" file.

This script needs two arguments:

- Ilias language file full path

- Full path of the file which contains all the deprecated values.

Example:

    python mark_deprecated_lang_vars.py /Users/Sites/ilias/lang/ilias.en.lang /Users/Doe/Desktop/lang_deprecated_leg.csv
