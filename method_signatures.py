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
            print "ERROR: Can't find class in %s" % path
            continue
        yield ClassInfo(path, res.group(2), res.group(4), c[res.end():])

def ancestor_name(info):
    res = class_info_re.match(info)
    if res is None:
        return None
    return res.group(1)

class ClassInfo(object):
    def __init__(self, path, name, info, content):
        self.path = path
        self.name = name
        self.ancestor_name = ancestor_name(info)
        self.methods = method_infos(path, content)

    def pp(self):
        return ("%s\n%s\n\tExtends: %s\n\tMethods:\n\t\t%s"
                % ( self.path
                  , self.name
                  , self.ancestor_name
                  , "\n\t\t".join([m.pp() for m in self.methods.values()])
                  ))

method_info_re = re.compile("function\s+(\w+)\s*\(([^)]*)\)")
params_re = re.compile("(\w+)?\s*[$](\w+)")

def method_infos(path, content):
    if class_re.search(content):
        print "ERROR: Duplicate class in %s" % path
    mis = {}
    while True:
        res = method_info_re.search(content)
        if res is None:
            return mis
        mi = MethodInfo(res.group(1), param_infos(path, res.group(2)))
        mis[mi.name] = mi
        content = content[res.end():]

def param_infos(path, content):
    params = []
    while True:
        res = params_re.search(content)
        if res is None:
            return params
        params.append((res.group(2), res.group(1)))
        content = content[res.end():]

class MethodInfo(object):
    def __init__(self, name, params):
        self.name = name
        self.params = params

    def pp(self):
        return ("%s (%s)" %
                ( self.name
                , ", ".join(["$%s" % p[0] if p[1] is None else "%s $%s" % (p[1], p[0]) for p in self.params])
                ))

CLASSES = {}

def make_ILIAS_CLASSES():
    for c in class_infos("/home/lechimp/Code/ILIAS"):
        CLASSES[c.name] = c

def find_signature_conflicts(cls_name):
    cls = CLASSES[cls_name]
    if cls.ancestor_name is None:
        return []

    conflicts = []

    ancestor = CLASSES[cls_name]
    while True:
        for name, method in cls.methods.items():
            if not name in ancestor.methods:
                continue
            anc_methods = ancestor.methods[name]
            if len(anc_methods.params) != len(method.params):
                conflicts.append("%s: amount of params differs regarding %s" % (name, ancestor.name)) 

        if ancestor.ancestor_name is None:
            return conflicts
        else:
            try:
                ancestor = CLASSES[ancestor.ancestor_name]
            except:
                conflicts.append("Unknown ancestor %s" % ancestor.ancestor_name)
                return conflicts

TOTAL_CONFLICTS = [0]

def find_all_signature_conflicts():
    TOTAL_CONFLICTS[0] = 0
    for n,cls in CLASSES.items():
        cs = find_signature_conflicts(n)
        TOTAL_CONFLICTS[0] += len(cs)
        yield (n, cls.path, cs)

def print_all_signature_conflicts():
    for (name, path, conflicts) in find_all_signature_conflicts():
        if len(conflicts) > 0:
            print "%s (%s)" % (name, path)
            print "\t" + ("\n\t".join(conflicts))
            print ""

    print "TOTAL: %d" % TOTAL_CONFLICTS[0]

if __name__ == "__main__":
    make_ILIAS_CLASSES()
    print_all_signature_conflicts()
