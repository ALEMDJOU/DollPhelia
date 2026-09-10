-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_ophelia_document ADD UNIQUE INDEX uk_ophelia_document_ref (ref);
ALTER TABLE llx_ophelia_document ADD INDEX idx_ophelia_document_status (status);
ALTER TABLE llx_ophelia_document ADD INDEX idx_ophelia_document_fk_template (fk_template);
