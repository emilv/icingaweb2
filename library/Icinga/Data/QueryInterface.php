<?php

namespace Icinga\Data;

use Countable;

interface QueryInterface extends Browsable, Fetchable, Filterable, Limitable, Sortable, Countable {};
