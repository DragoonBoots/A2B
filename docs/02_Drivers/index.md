Source
------
<table>
    <thead>
        <tr>
            <th>Driver</th>
            <th>Scheme(s)</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><a href="01_Source/DbalSourceDriver">DbalSourceDriver</a></td>
            <td>
                <code>db2</code>
                <code>mssql</code>
                <code>mysql</code>
                <code>pgsql</code>
                <code>sqlite</code>
                and <a href="https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/configuration.html#connecting-using-a-url">others</a>
            </td>
            <td>Anything supported by the <a href="https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/introduction.html#introduction">Doctrine DBAL</a>
        </tr>
    </tbody>
</table>

Destination
-----------
<table>
    <thead>
        <tr>
            <th>Driver</th>
            <th>Scheme(s)</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><a href="01_Destination/CsvDestinationDriver">CsvDestinationDriver</a></td>
            <td><code>csv</code></td>
            <td>One CSV file per migration</td>
        </tr>
    </tbody>
</table>
