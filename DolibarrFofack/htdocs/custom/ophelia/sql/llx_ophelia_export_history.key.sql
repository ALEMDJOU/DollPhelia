-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_ophelia_export_history ADD INDEX idx_ophelia_export_history_fk_result (fk_result);
ALTER TABLE llx_ophelia_export_history ADD CONSTRAINT fk_ophelia_export_history_result FOREIGN KEY (fk_result) REFERENCES llx_ophelia_extraction_result (rowid);
