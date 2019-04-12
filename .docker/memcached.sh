#!/bin/bash
set -e
/usr/bin/memcached -u memcache -v
exec "$@"
