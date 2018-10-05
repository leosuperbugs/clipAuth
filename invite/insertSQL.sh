#!/bin/bash

for line in `cat code`
do
	mysql -e "insert into test.code (id, invitationCode, isUsed) values (null, '$line', 'false')"
done

