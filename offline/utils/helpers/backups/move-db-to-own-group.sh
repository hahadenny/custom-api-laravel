#!/bin/bash

STOP GROUP_REPLICATION;

# must be valid uuid
SET PERSIST group_replication_group_name='e22d55a3-e6ea-49db-9f2a-378382820bce';

SET PERSIST group_replication_bootstrap_group=ON;

START GROUP_REPLICATION;
