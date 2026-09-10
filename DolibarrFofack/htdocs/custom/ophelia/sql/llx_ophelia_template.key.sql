-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_ophelia_template ADD UNIQUE INDEX uk_ophelia_template_ref (ref);
ALTER TABLE llx_ophelia_template ADD INDEX idx_ophelia_template_doc_type (doc_type);
ALTER TABLE llx_ophelia_template ADD INDEX idx_ophelia_template_active (active);
