<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactForm extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $lastName;
    public $email;
    public $phone;
    public $comments;

    public function __construct($name, $lastName, $email, $phone, $comments)
    {
        $this->name = $name;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phone = $phone;
        $this->comments = $comments;
    }

    public function build()
    {
        return $this->subject('Nuevo contacto desde la web - ' . $this->name . ' ' . $this->lastName)
            ->markdown('emails.contact-form')
            ->replyTo($this->email, $this->name . ' ' . $this->lastName);
    }
}
