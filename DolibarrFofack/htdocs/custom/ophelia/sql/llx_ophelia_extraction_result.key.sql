-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_ophelia_extraction_result ADD INDEX idx_ophelia_extraction_result_fk_document (fk_document);
ALTER TABLE llx_ophelia_extraction_result ADD CONSTRAINT fk_ophelia_extraction_result_document FOREIGN KEY (fk_document) REFERENCES llx_ophelia_document (rowid);
