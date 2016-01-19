#################################################################
#
# (c) Richard Klees <richard.klees@concepts-and-training.de
#
# This is licensed as GPL v3
#
# USE WITH CARE, MAKE SURE YOU UNDERSTAND WHAT THIS IS DOING
# AND CLAIMING AND WHAT THIS IS NOT DOING AND CLAIMING.
#
# Use as python method_signatures.py $ILIAS_FOLDER
#
# This script makes a quick, dirty and naive assessment of the
# "message signature" problem when achieving PHP7 compliance.
# It only regards classes in class.*.php files with one class
# in the file. It does not parse the PHP code but rather uses
# regexes to gather the required information.
#
# It creates a class_name => ClassInfo map of all found classes.
# The ClassInfo contains the name of the class, the path where it
# was found, a list of ancestor classes and a list of method
# signatures.
# It then uses this map to traverse the list of ancestors for
# every class and checks if the length of the parameter lists
# matches the length of the methods in the ancestor classes.
#
##################################################################

import os
import re
import sys

ILIAS_PATH = [None]

# Gathering of class files in the ILIAS-folder

class_file_re = re.compile("class\..*\.php")

def class_files(path):
    """ Generator for all class files in the ILIAS-folder. """
    if os.path.isdir(path):
        fs = os.listdir(path)
        for _f in fs:
            f = os.path.join(path, _f)
            for p in class_files(f):
                yield p
            else:
                if class_file_re.match(_f):
                    yield os.path.join(path, f)


# Deconstruction of the code in the class.

# A class could be abstract or final, then there is a 'class' keyword, a class
# name and some additional stuff (extends ... implements ...), badly dupped
# 'class_info'.
class_re = re.compile("^\s*(abstract|final)?\s*class\s+(\w+)(\s|/)+(.*)$", re.MULTILINE)

# We are only interested in the 'extends' part of a class definition, there
# can only be one class that is extended.
class_info_re = re.compile(".*extends\s+(\w+)")

def class_file_contents(path):
    """ Generator for the contents of class files. """
    for cf in class_files(path):
        with open(cf) as f:
            yield (cf, f.read())

def class_infos(path):
    """ Get class infos from class_files. """
    for (path, c) in class_file_contents(path):
        res = class_re.search(c)
        if not res:
            # We don't handle these, there aren't many of them and
            # this is quick and dirty!
            print "ERROR: Can't find class in %s" % path
            continue
        yield ClassInfo(path, res.group(2), res.group(4), c[res.end():])

def ancestor_name(info):
    """ Get the ancestor name from a class info. """
    res = class_info_re.match(info)
    if res is None:
        return None
    return res.group(1)

class ClassInfo(object):
    """ All infos gathered about a class. """
    def __init__(self, path, name, info, content):
        self.path = path
        self.name = name
        self.ancestor_name = ancestor_name(info)
        self.methods = method_infos(path, content)

    def pp(self):
        """ 'Pretty' print. """
        return ("%s\n%s\n\tExtends: %s\n\tMethods:\n\t\t%s"
                % ( self.path
                  , self.name
                  , self.ancestor_name
                  , "\n\t\t".join([m.pp() for m in self.methods.values()])
                  ))

    def all_ancestors(self):
        """ Recursively get all ancestors using the CLASSES global. """
        yield self.ancestor_name
        if self.ancestor_name in CLASSES:
            for a in CLASSES[self.ancestor_name].all_ancestors():
                yield a

# Deconstruction of methods

# We search for the function keyword a name and a parameter list following it.
method_info_re = re.compile("function\s+(\w+)\s*\(([^)]*)\)")
# We deconstruct the parameters regarding type hints and parameter names, but
# NOT REGARDING DEFAULTS!!
params_re = re.compile("(\w+)?\s*[$](\w+)")

def method_infos(path, content):
    """ Gather information on all methods in a class. """
    if class_re.search(content):
        # We only expect on class per file, that is, if there
        # is the 'class'-keyword in content, it's not ok...
        print "ERROR: Duplicate class in %s" % path
    mis = {}
    while True:
        # search next method signature
        res = method_info_re.search(content)
        if res is None:
            # and done...
            return mis
        # Create info and deconstruct the parameters as well.
        mi = MethodInfo(res.group(1), param_infos(path, res.group(2)))
        mis[mi.name] = mi
        # We already searched the content to the end of the match.
        content = content[res.end():]

def param_infos(path, content):
    """ Gather information about parameters.

        DOES NOT TAKE CARE OF DEFAULTS IN PARAMETERLISTS!
    """
    params = []
    while True:
        res = params_re.search(content)
        if res is None:
            # and done
            return params
        # We use the name of the param and an (optional) type hint.
        params.append((res.group(2), res.group(1)))
        # We already searched the content to the end of the match.
        content = content[res.end():]

class MethodInfo(object):
    """ Information about a method. """
    def __init__(self, name, params):
        self.name = name
        self.params = params

    def pp(self):
        """ 'Pretty' print. """
        return ("%s (%s)" %
                ( self.name
                , ", ".join(["$%s" % p[0] if p[1] is None else "%s $%s" % (p[1], p[0]) for p in self.params])
                ))

# Global class map. Build it with make_ILIAS_CLASSES()
CLASSES = {}

def make_ILIAS_CLASSES():
    """ Build global CLASSES map using files found in ILIAS_PATH[0] """
    for c in class_infos(ILIAS_PATH[0]):
        CLASSES[c.name] = c

# Some tags for errors.
CONFLICT_UNKNOWN_ANCESTOR = 1
CONFLICT_PARAM_LEN_DIFFERS = 2

def find_signature_conflicts(cls_name):
    """ Find conflicts in signature for the class with the given name. """
    # This is the class under scrutiny.
    cls = CLASSES[cls_name]

    # If the class has no ancestor, there are no conflicts for sure.
    if cls.ancestor_name is None:
        return []

    conflicts = []

    # The ancestor class we are currently checking.
    ancestor = CLASSES[cls_name]
    while True:
        # Check every method in the class under scrutiny.
        for name, method in cls.methods.items():
            # If the ancestor does not define the method under scrutiny,
            # there is no conflict.
            if not name in ancestor.methods:
                continue
            anc_methods = ancestor.methods[name]
            # This is the only check we currently make, the length of
            # the parameter lists.
            if len(anc_methods.params) != len(method.params):
                conflicts.append((CONFLICT_PARAM_LEN_DIFFERS,
                    "%s: amount of params differs regarding %s" % (name, ancestor.name)))

        # We reached the end of the chain of ancestors...
        if ancestor.ancestor_name is None:
            # ... and were done.
            return conflicts
        else:
            # Could be, that there is an ancestor, but it is not ILIAS-class.
            try:
                ancestor = CLASSES[ancestor.ancestor_name]
            except:
                # We note that as conflict as well.
                conflicts.append((CONFLICT_UNKNOWN_ANCESTOR,
                    "Unknown ancestor %s" % ancestor.ancestor_name))
                return conflicts


def find_all_signature_conflicts():
    """ Search conflicts in all classes. """
    for n, cls in CLASSES.items():
        cs = find_signature_conflicts(n)
        yield (cls, cs)

def signature_conflicts_flat():
    """ Flatten the list of conflicts.
        That is: [(n, [c1,c2,c3])] -> [(n,c1),(n,c2),(n,c3)]
    """
    for cls, cs in find_all_signature_conflicts():
        for c in cs:
            yield (cls, c)

def filter_with_base_class(bc_name, cs):
    """ Filter conflicts regarding base classes. """
    return [c for c in cs if bc_name in c[0].all_ancestors()]

def print_all_signature_conflicts():
    """ Print all found conflicts and some accumulations. """
    for (cls, conflicts) in find_all_signature_conflicts():
        if len(conflicts) > 0:
            print "%s (%s)" % (cls.name, cls.path)
            print "\t" + ("\n\t".join([p[1] for p in conflicts]))
            print ""

    conflicts_flat = [c for c in signature_conflicts_flat()]
    print "TOTAL: %d" % len(conflicts_flat)

    # Exceptions
    conflicts_exceptions = filter_with_base_class("Exception", conflicts_flat)
    print "IN Exceptions: %d" % len(conflicts_exceptions)

    # ListGUI
    conflicts_list_gui = filter_with_base_class("ilObjectListGUI", conflicts_flat)
    print "IN ilObjectListGUIs: %d" % len(conflicts_list_gui)

    # ObjectGUI
    conflicts_object_gui = filter_with_base_class("ilObjectGUI", conflicts_flat)
    print "IN ilObjectGUIs: %d" % len(conflicts_object_gui)

    # TableGUI
    conflicts_table_gui = filter_with_base_class("ilTable2GUI", conflicts_flat)
    print "IN ilTable2GUIs: %d" % len(conflicts_table_gui)

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print "Use like python method_signatures.py $ILIAS_PATH"
        exit()
    ILIAS_PATH[0] = sys.argv[1]
    make_ILIAS_CLASSES()
    print_all_signature_conflicts()
