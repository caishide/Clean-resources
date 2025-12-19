<?php

namespace Tests\Unit\Rules;

use Tests\TestCase;
use App\Rules\FileTypeValidate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class FileTypeValidateTest extends TestCase
{
    /** @test */
    public function it_accepts_valid_file_types()
    {
        $rule = new FileTypeValidate(['jpg', 'png', 'pdf']);

        $file = UploadedFile::fake()->create('test.jpg', 100);

        $result = $rule->passes('file', $file);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_invalid_file_types()
    {
        $rule = new FileTypeValidate(['jpg', 'png']);

        $file = UploadedFile::fake()->create('test.pdf', 100);

        $result = $rule->passes('file', $file);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_rejects_executable_files()
    {
        $rule = new FileTypeValidate(['jpg', 'png']);

        $file = UploadedFile::fake()->create('malicious.php', 100);

        $result = $rule->passes('file', $file);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_validates_using_validator()
    {
        $data = [
            'file' => UploadedFile::fake()->create('test.jpg', 100),
        ];

        $validator = Validator::make($data, [
            'file' => [new FileTypeValidate(['jpg', 'png'])],
        ]);

        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function it_provides_custom_error_message()
    {
        $rule = new FileTypeValidate(['jpg', 'png']);

        $file = UploadedFile::fake()->create('test.pdf', 100);

        $rule->passes('file', $file);

        $this->assertNotNull($rule->message());
        $this->assertStringContainsString('file type', $rule->message());
    }

    /** @test */
    public function it_checks_mime_type_not_just_extension()
    {
        $rule = new FileTypeValidate(['jpg']);

        // Create a file with jpg extension but wrong mime type
        $file = UploadedFile::fake()->create('test.jpg', 100, 'text/plain');

        $result = $rule->passes('file', $file);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_accepts_files_with_correct_mime_type()
    {
        $rule = new FileTypeValidate(['jpg']);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $result = $rule->passes('file', $file);

        $this->assertTrue($result);
    }
}
