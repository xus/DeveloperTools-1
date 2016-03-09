#!/bin/sh
#execute this file in the root folder of your git repository.
#	it will automatically copy all the hooks in git_hooks/hooks folder 
#	to their appropriate location in .git/hooks/
for file in $(ls ./git_hooks/hooks/)
do
   	cp git_hooks/hooks/${file} ./.git/hooks/
	chmod +x .git/hooks/${file}
done
