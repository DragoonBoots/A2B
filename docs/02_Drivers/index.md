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
            <td><a href="Source/DbalSourceDriver">DbalSourceDriver</a></td>
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
        <tr>
            <td><a href="Source/CsvSourceDriver">CsvDestinationDriver</a></td>
            <td><code>csv</code></td>
            <td>A CSV file</td>
        </tr>
        <tr>
            <td><a href="Source/YamlSourceDriver">YamlSourceDriver</a></td>
            <td><code>yaml</code><code>yml</code></td>
            <td>A directory with YAML files</td>
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
            <td><a href="Destination/CsvDestinationDriver">CsvDestinationDriver</a></td>
            <td><code>csv</code></td>
            <td>One CSV file per migration</td>
        </tr>
        <tr>
            <td><a href="Destination/YamlDestinationDriver">YamlDestinationDriver</a></td>
            <td><code>yaml</code><code>yml</code></td>
            <td>A directory (with optional subdirectories depending on migration ids) with one YAML file per entity.</td>
        </tr>
        <tr>
            <td><a href="Destination/DoctrineDestinationDriver">DoctrineDestinationDriver</a></td>
            <td><code>doctrine</code></td>
            <td><a href="https://www.doctrine-project.org/projects/doctrine-orm/en/current/index.html">Doctrine ORM</a> entities</td>
        </tr>
        <tr>
            <td><a href="Destination/DbalDestinationDriver">DbalDestinationDriver</a></td>
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
