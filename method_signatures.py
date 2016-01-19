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

class_re = re.compile("^\s*(abstract|final)?\s*class\s+(\w+)(\s|/)+(.*)$", re.MULTILINE)
class_info_re = re.compile(".*extends\s+(\w+)")

def class_file_contents(path):
    for cf in class_files(path):
        with open(cf) as f:
            yield (cf, f.read())

def class_infos(path):
    for (path, c) in class_file_contents(path):
        res = class_re.search(c)
        if not res:
            print "ERROR in %s" % path
            continue
        yield ClassInfo(path, res.group(2), res.group(4))

def ancestor_name(info):
    res = class_info_re.match(info)
    if res is None:
        return None
    return res.group(1)

class ClassInfo(object):
    def __init__(self, path, name, info):
        self.path = path
        self.name = name
        self.ancestor_name = ancestor_name(info)

    def pp(self):
        return "%s\n%s\n\t%s" % (self.path, self.name, self.ancestor_name)

CLASSES = {}

def make_ILIAS_CLASSES():
    for c in class_infos("/home/lechimp/Code/ILIAS"):
        CLASSES[c.name] = c

if __name__ == "__main__":
    make_ILIAS_CLASSES()
    for c in class_infos("/home/lechimp/Code/ILIAS"):
        CLASSES[c.name] = c
        print "-------------------------------------------"
        print c.pp()
