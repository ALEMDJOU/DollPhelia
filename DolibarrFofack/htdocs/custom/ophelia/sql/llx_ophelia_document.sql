-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_ophelia_document(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	ref VARCHAR(128) NOT NULL,
	label VARCHAR(255),
	filename VARCHAR(255) NOT NULL,
	filepath VARCHAR(512) NOT NULL,
	filetype VARCHAR(50),
	filesize BIGINT,
	doc_type VARCHAR(50),
	status SMALLINT DEFAULT 0 NOT NULL,
	fk_user_upload INTEGER,
	fk_template INTEGER DEFAULT NULL,
	matching_score DECIMAL(5,4) DEFAULT NULL,
	strategy_used VARCHAR(50) DEFAULT NULL,
	task_id VARCHAR(255) DEFAULT NULL,
	date_upload DATETIME NOT NULL,
	date_processing DATETIME DEFAULT NULL,
	date_validation DATETIME DEFAULT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
