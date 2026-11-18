<?php

namespace Bernskiold\ExportEngine\Support;

use Closure;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX;
use ReflectionClass;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Works with OpenSpout 4 and OpenSpout 5.
 *
 * OpenSpout 5 made its styles, sheet views and options read-only. They take
 * their values in the constructor, and a `with…()` method makes a changed
 * copy. OpenSpout 4 uses setters. The engine uses only the methods in this
 * class, so it works with the two versions. Use the API of your version in
 * your own exporters.
 */
class OpenSpout
{
    protected static ?bool $isVersion5 = null;

    public static function isVersion5(): bool
    {
        return static::$isVersion5 ??= class_exists(BorderName::class);
    }

    /**
     * Bold text with a medium line below it, for a header row.
     */
    public static function headerStyle(): Style
    {
        if (static::isVersion5()) {
            return new Style(
                fontBold: true,
                border: new Border(new BorderPart(name: BorderName::BOTTOM, width: BorderWidth::MEDIUM)),
            );
        }

        return (new Style)
            ->setFontBold()
            ->setBorder(new Border(
                new BorderPart(name: Border::BOTTOM, width: Border::WIDTH_MEDIUM)
            ));
    }

    /**
     * A sheet view that freezes the rows above $row and the columns to the
     * left of $column.
     */
    public static function frozenSheetView(int $row, string $column): XLSX\Entity\SheetView
    {
        if (static::isVersion5()) {
            return new XLSX\Entity\SheetView(freezeRow: $row, freezeColumn: $column);
        }

        $sheetView = new XLSX\Entity\SheetView;
        $sheetView->setFreezeRow($row);
        $sheetView->setFreezeColumn($column);

        return $sheetView;
    }

    /**
     * Open a writer for a file. An Excel writer gets the creator, and uses
     * shared strings in place of inline strings. Inline strings make large
     * workbooks much bigger and some tools cannot read them.
     *
     * OpenSpout 5 takes the Excel options only in the constructor of the
     * writer, but SimpleExcel makes the writer with no options. Thus the
     * engine replaces the writer of SimpleExcel before it opens the file.
     *
     * An Excel writer builds the file in $tempFolder, or in the temporary
     * directory of the system when it is null.
     *
     * @param  Closure(\OpenSpout\Writer\WriterInterface): void|null  $configureWriter
     */
    public static function createWriter(
        string $path,
        string $type,
        string $creator,
        ?string $delimiter = null,
        ?Closure $configureWriter = null,
        ?string $tempFolder = null,
    ): SimpleExcelWriter {
        if (! static::isVersion5() || $type !== 'xlsx') {
            return SimpleExcelWriter::create(
                file: $path,
                type: $type,
                configureWriter: function ($writer) use ($creator, $configureWriter, $tempFolder) {
                    if ($writer instanceof XLSX\Writer) {
                        $writer->setCreator($creator);
                        $writer->getOptions()->SHOULD_USE_INLINE_STRINGS = false;

                        if ($tempFolder !== null) {
                            $writer->getOptions()->setTempFolder($tempFolder);
                        }
                    }

                    if ($configureWriter !== null) {
                        $configureWriter($writer);
                    }
                },
                delimiter: $delimiter,
            );
        }

        $writer = new XLSX\Writer(new XLSX\Options(
            SHOULD_USE_INLINE_STRINGS: false,
            properties: new XLSX\Properties(creator: $creator, lastModifiedBy: $creator),
            tempFolder: $tempFolder,
        ));

        $simpleExcelWriter = (new ReflectionClass(SimpleExcelWriter::class))->newInstanceWithoutConstructor();

        Closure::bind(function () use ($path, $writer) {
            $this->path = $path;
            $this->writer = $writer;
            $this->csvOptions = new \OpenSpout\Writer\CSV\Options;
        }, $simpleExcelWriter, SimpleExcelWriter::class)();

        if ($configureWriter !== null) {
            $configureWriter($writer);
        }

        $writer->openToFile($path);

        return $simpleExcelWriter;
    }

    /**
     * Stop a writer that failed, without finishing its file.
     *
     * SimpleExcel closes its writer when PHP destroys it, and closing an
     * Excel writer zips everything written so far. After a failure that is
     * wasted work on a file nobody wants, and it fails again when the disk
     * is full. Thus the writer gets a new writer that never opened, which
     * has nothing to close. PHP closes the files of the old writer when it
     * destroys it.
     */
    public static function discard(SimpleExcelWriter $writer): void
    {
        Closure::bind(function () {
            $this->writer = new \OpenSpout\Writer\CSV\Writer;
        }, $writer, SimpleExcelWriter::class)();
    }

    /**
     * Forget the version found, for tests that change it.
     */
    public static function flush(): void
    {
        static::$isVersion5 = null;
    }
}
