# Globals to Dependency Injection Container

According to [Jour Fixe](http://www.ilias.de/docu/goto.php?target=wiki_1357_JourFixe-2015-08-03)
we want to introduce a Dependency Injection Container starting with ILIAS 5.2..

This script helps developers to replace globals by the DIC. Use as follows:

* apply php global_to_dic.php YOUR_FOLDER
* look into your git diff, the tool might be leaving some warnings at suspicious
  locations
* make sure you understand that you are still responsible for your changes
  yourself, this tool just tries to help you
* then git commit, 