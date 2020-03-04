1.x to 2.x
----------

- Sources and Destinations are no longer URLs.  This allows using PHP streams
  or other complex sources.  It also cleans up many edge cases around what is a
  valid URL (e.g. class names).
- Because of this, drivers now must be specified in the migration annotation.
- If your project uses custom drivers, note that they no longer depend on
  the URI Parser.  Update your code accordingly.
    - Driver annotations no longer specify supported schemes. 
    - The `sourceIds` and `destIds` properties of source and destination drivers
      respectively have both been renamed to `ids` to simplify maintenance.
    - The `sourceUri` and `destUri` properties of source and destination drivers
      respectively have both been removed.  Get the source/destination from the
      migration definition instead.
