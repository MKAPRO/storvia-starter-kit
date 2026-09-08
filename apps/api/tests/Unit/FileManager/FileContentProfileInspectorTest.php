<?php

namespace Tests\Unit\FileManager;

use App\Services\FileManager\FileContentProfileInspector;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FileContentProfileInspectorTest extends TestCase
{
    #[DataProvider('validOoxmlProvider')]
    public function test_valid_ooxml_container_matches_its_extension(string $name, string $base64): void
    {
        $stream = $this->streamFromBase64($base64);

        try {
            (new FileContentProfileInspector)->assertMatchesExtension($stream, $name);
            $this->assertSame(0, ftell($stream));
        } finally {
            fclose($stream);
        }
    }

    public function test_plain_zip_renamed_to_docx_is_rejected(): void
    {
        $stream = $this->streamFromBase64(
            'UEsDBBQAAAAIAIdqI12GphA2BwAAAAUAAAAJAAAAaGVsbG8udHh0y0jNyckHAFBLAQIUAxQAAAAIAIdqI12GphA2BwAAAAUAAAAJAAAAAAAAAAAAAACAAQAAAABoZWxsby50eHRQSwUGAAAAAAEAAQA3AAAALgAAAAAA',
        );

        try {
            $this->expectException(ValidationException::class);
            (new FileContentProfileInspector)->assertMatchesExtension($stream, 'spoofed.docx');
        } finally {
            fclose($stream);
        }
    }

    public function test_ooxml_container_for_another_family_is_rejected(): void
    {
        $stream = $this->streamFromBase64(
            'UEsDBBQAAAAIAIdqI13LBSEovgAAAAUBAAATAAAAW0NvbnRlbnRfVHlwZXNdLnhtbF2PsU4EMQxEe74icot2vVAghC53BRIlUBwfYCXevYiNHSXhgL/HC9IVlNbMvBnvDl95dWeuLal4uBkncCxBY5LFw9vxabiHw/5qd/wu3Jx5pXk49V4eEFs4caY2amExZdaaqdtZFywU3mlhvJ2mOwwqnaUPfWOAwV6sr6bI7pVqf6bMHvBTa8So4SObdTQcuMe/3FbtgUpZU6BuM/Es8V/poPOcAl/yG61UDdyaPZLX8aJkSnK94dGG4O9b+x9QSwMEFAAAAAgAh2ojXYLZHNUSAAAAEAAAAAsAAABfcmVscy8ucmVsc7MJSs1JLMnMzyvOyCwo1rcDAFBLAwQUAAAACACHaiNdRCmNNQkAAAAHAAAAEQAAAHdvcmQvZG9jdW1lbnQueG1ss8lNzMzTtwMAUEsBAhQDFAAAAAgAh2ojXcsFISi+AAAABQEAABMAAAAAAAAAAAAAAIABAAAAAFtDb250ZW50X1R5cGVzXS54bWxQSwECFAMUAAAACACHaiNdgtkc1RIAAAAQAAAACwAAAAAAAAAAAAAAgAHvAAAAX3JlbHMvLnJlbHNQSwECFAMUAAAACACHaiNdRCmNNQkAAAAHAAAAEQAAAAAAAAAAAAAAgAEqAQAAd29yZC9kb2N1bWVudC54bWxQSwUGAAAAAAMAAwC5AAAAYgEAAAAA',
        );

        try {
            $this->expectException(ValidationException::class);
            (new FileContentProfileInspector)->assertMatchesExtension($stream, 'spoofed.xlsx');
        } finally {
            fclose($stream);
        }
    }

    public function test_non_container_type_is_a_noop_and_preserves_stream_position(): void
    {
        $stream = tmpfile();
        $this->assertIsResource($stream);
        fwrite($stream, 'plain text');
        fseek($stream, 3, SEEK_SET);

        try {
            (new FileContentProfileInspector)->assertMatchesExtension($stream, 'note.txt');
            $this->assertSame(3, ftell($stream));
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return iterable<string, array{string,string}>
     */
    public static function validOoxmlProvider(): iterable
    {
        yield 'docx' => [
            'document.docx',
            'UEsDBBQAAAAIAIdqI13LBSEovgAAAAUBAAATAAAAW0NvbnRlbnRfVHlwZXNdLnhtbF2PsU4EMQxEe74icot2vVAghC53BRIlUBwfYCXevYiNHSXhgL/HC9IVlNbMvBnvDl95dWeuLal4uBkncCxBY5LFw9vxabiHw/5qd/wu3Jx5pXk49V4eEFs4caY2amExZdaaqdtZFywU3mlhvJ2mOwwqnaUPfWOAwV6sr6bI7pVqf6bMHvBTa8So4SObdTQcuMe/3FbtgUpZU6BuM/Es8V/poPOcAl/yG61UDdyaPZLX8aJkSnK94dGG4O9b+x9QSwMEFAAAAAgAh2ojXYLZHNUSAAAAEAAAAAsAAABfcmVscy8ucmVsc7MJSs1JLMnMzyvOyCwo1rcDAFBLAwQUAAAACACHaiNdRCmNNQkAAAAHAAAAEQAAAHdvcmQvZG9jdW1lbnQueG1ss8lNzMzTtwMAUEsBAhQDFAAAAAgAh2ojXcsFISi+AAAABQEAABMAAAAAAAAAAAAAAIABAAAAAFtDb250ZW50X1R5cGVzXS54bWxQSwECFAMUAAAACACHaiNdgtkc1RIAAAAQAAAACwAAAAAAAAAAAAAAgAHvAAAAX3JlbHMvLnJlbHNQSwECFAMUAAAACACHaiNdRCmNNQkAAAAHAAAAEQAAAAAAAAAAAAAAgAEqAQAAd29yZC9kb2N1bWVudC54bWxQSwUGAAAAAAMAAwC5AAAAYgEAAAAA',
        ];
        yield 'xlsx' => [
            'workbook.xlsx',
            'UEsDBBQAAAAIAIdqI12j9f6xwgAAAP0AAAATAAAAW0NvbnRlbnRfVHlwZXNdLnhtbF2PsW7DMAxE936FoDWw6GQoiiJKhgIdmw7pBzASbQu2SEFS0/TvK6dbJoLg3bvj/niLi7pSLkHY6q3ptSJ24gOPVn+d37sXfTw87c+/iYpqWi5WT7WmV4DiJopYjCTidhkkR6xtzSMkdDOOBLu+fwYnXIlrV1eGbrBTy8vBk/rEXD8wktVwW+BH8nwRmU2DafX271qDrcaUluCwtpJwZf8Q2ckwBEde3HdsFlNSJvRlIqpxMfdpIgberGBoBeD+zuEPUEsDBBQAAAAIAIdqI12C2RzVEgAAABAAAAALAAAAX3JlbHMvLnJlbHOzCUrNSSzJzM8rzsgsKNa3AwBQSwMEFAAAAAgAh2ojXUQpjTUJAAAABwAAAA8AAAB4bC93b3JrYm9vay54bWyzyU3MzNO3AwBQSwECFAMUAAAACACHaiNdo/X+scIAAAD9AAAAEwAAAAAAAAAAAAAAgAEAAAAAW0NvbnRlbnRfVHlwZXNdLnhtbFBLAQIUAxQAAAAIAIdqI12C2RzVEgAAABAAAAALAAAAAAAAAAAAAACAAfMAAABfcmVscy8ucmVsc1BLAQIUAxQAAAAIAIdqI11EKY01CQAAAAcAAAAPAAAAAAAAAAAAAACAAS4BAAB4bC93b3JrYm9vay54bWxQSwUGAAAAAAMAAwC3AAAAZAEAAAAA',
        ];
        yield 'pptx' => [
            'slides.pptx',
            'UEsDBBQAAAAIAIdqI12tCAzqvAAAAAoBAAATAAAAW0NvbnRlbnRfVHlwZXNdLnhtbF2PsU4EMQxEe74iSos2XigQQpe7AokSKI4PsLLevYiNYyXmBH+P92iAcjTjeePd4bOs7kyt58rR34TRO+JUp8xL9G/Hp+HeH/ZXu+OXUHeW5R79SVUeAHo6UcEeqhCbM9dWUE22BQTTOy4Et+N4B6myEuugW4e3shfjtTyRe8Wmz1goehBRkEbdgqi2JVijd48/pxs9ehRZc7q4cObpH3eo85wTTTV9FDsJv8vK+keGgpmvNwDYGrj8tv8GUEsDBBQAAAAIAIdqI12C2RzVEgAAABAAAAALAAAAX3JlbHMvLnJlbHOzCUrNSSzJzM8rzsgsKNa3AwBQSwMEFAAAAAgAh2ojXUQpjTUJAAAABwAAABQAAABwcHQvcHJlc2VudGF0aW9uLnhtbLPJTczM07cDAFBLAQIUAxQAAAAIAIdqI12tCAzqvAAAAAoBAAATAAAAAAAAAAAAAACAAQAAAABbQ29udGVudF9UeXBlc10ueG1sUEsBAhQDFAAAAAgAh2ojXYLZHNUSAAAAEAAAAAsAAAAAAAAAAAAAAIAB7QAAAF9yZWxzLy5yZWxzUEsBAhQDFAAAAAgAh2ojXUQpjTUJAAAABwAAABQAAAAAAAAAAAAAAIABKAEAAHBwdC9wcmVzZW50YXRpb24ueG1sUEsFBgAAAAADAAMAvAAAAGMBAAAAAA==',
        ];
    }

    /** @return resource */
    private function streamFromBase64(string $base64)
    {
        $bytes = base64_decode($base64, true);
        $this->assertIsString($bytes);
        $stream = tmpfile();
        $this->assertIsResource($stream);
        fwrite($stream, $bytes);
        rewind($stream);

        return $stream;
    }
}
