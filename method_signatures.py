import os
import re

class_file_re = re.compile("class\..*\.php")

def class_files(path):
    if os.path.isdir(path):
        fs = os.listdir(path)
        for _f in fs:
            f = os.path.join(path, _f)
            for p in class_files(f):
                yield p
            else:
                if class_file_re.match(_f):
                    yield os.path.join(path, f)

class_info_re = re.compile("^\s*(abstract)?\s*class\s+(\w+)\s+(.*)$", re.MULTILINE)

def class_file_contents(path):
    for cf in class_files(path):
        with open(cf) as f:
            yield (cf, f.read())

def class_infos(path):
    for (path, c) in class_file_contents(path):
        res = class_info_re.search(c)
        if not res:
            print "ERROR in %s" % path
            continue
        yield ClassInfo(path, res.group(2), res.group(3)) 

class ClassInfo(object):
    def __init__(self, path, name, info):
        self.path = path
        self.name = name

    def pp(self):
        return "%s\n%s" % (self.path, self.name)

if __name__ == "__main__":
    for c in class_infos("/home/lechimp/Code/ILIAS"):
        print "-------------------------------------------"
        print c.pp()
