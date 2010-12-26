-- this is just an example
-- such a file would contain as many SQL queries as needed for a smooth
-- transition from an older to a newer version of the library

UPDATE Baobab_Errors SET msg="1.0" WHERE code=1000;