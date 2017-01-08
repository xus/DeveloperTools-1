__author__ = 'xus'
import fileinput
import sys
import csv
import os.path

if len(sys.argv) is not 3:
    sys.exit("Error: Invalid number of arguments")

LANG_FILE = sys.argv[1]
CSV_DEPRECATEDS = sys.argv[2]

if not os.path.isfile(LANG_FILE):
    sys.exit("Error: Lang File not found => "+LANG_FILE)

if not os.path.isfile(CSV_DEPRECATEDS):
    sys.exit("Error: File with deprecated values is not found => "+CSV_DEPRECATEDS)

deprecated = set(line.strip() for line in open(CSV_DEPRECATEDS))

fh = fileinput.input(LANG_FILE, inplace=True)
for line in fh:
    cols = line.split("#:#")
    if (len(cols) == 3 and cols[0]+","+cols[1] in deprecated):
        line = line.replace('\n', '')
        sys.stdout.write(line + "###deprecated \n")
    else:
        sys.stdout.write(line)
fh.close()
print "Done."