#!/usr/bin/env python

import hashlib, sys, random

LOCAL = 0x7F000001

CLS_GENERIC = 0x0
CLS_NO_WITHDRAW = 0x1
CLS_NO_DEPOSIT = 0x2
CLS_ALLOW_NEGATIVE = 0x4

def gen(name):
    secret = "".join([random.choice('0123456789abcdef') for i in range(32)])
    print >> sys.stderr, "define('%s_SECRET', '%s');" % (name, secret)
    return hashlib.md5(secret).hexdigest()[:16]

# ID, class, currency, creator_id, ip, comment, auth_code, admin_code, access_code, withdraw_code, block_code, create_code

TYPES = [
    ('DEP', CLS_NO_DEPOSIT | CLS_ALLOW_NEGATIVE, 1, 1, LOCAL, 'DEPOSITS', None, None, None, None, None, gen('DEP_TYPE_CREATE')),
    ('MAS', CLS_GENERIC, 1, 1, LOCAL, None, None, None, None, None, None, None),
    ('ORD', CLS_GENERIC, 1, 1, LOCAL, None, None, None, None, None, None, None),
    ('USR', CLS_GENERIC, 1, 1, LOCAL, None, None, None, None, None, None, None),
]

gen('DEP_ACCESS')
gen('DEP_WITHDRAW')

def line(a):
    return "\t".join([str(i) if i is not None else '' for i in a])

print "\n".join(map(line, TYPES))
