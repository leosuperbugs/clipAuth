import hashlib

m = hashlib.md5()

for i in range(0, 1000) :
    stri = str(i)
    m.update(stri)
    result = m.hexdigest()[0:6]
    print result
